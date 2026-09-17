<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Str;
use App\Core\Version;
use App\Repositories\BackupRepository;

/**
 * Backup and restore.
 *
 * The database dump is written in pure PHP through PDO - no mysqldump, no
 * shell access - because shared hosting rarely allows exec(). Files are
 * archived with ZipArchive when available and a tar fallback otherwise.
 *
 * Backups are written under storage/backups, or to a directory outside the
 * web root when the installer found one, and are never served directly: the
 * download route streams them after an authorisation check.
 */
final class BackupService
{
    public const TYPE_DATABASE = 'database';
    public const TYPE_FILES    = 'files';
    public const TYPE_FULL     = 'full';
    public const TYPE_UPDATE   = 'update';

    /** Paths never included in a files backup. */
    private const EXCLUDED_DIRECTORIES = [
        'storage/backups',
        'storage/cache',
        'storage/sessions',
        'storage/tmp',
        'storage/update',
        '.git',
        'node_modules',
        'vendor/bin',
    ];

    public function __construct(
        private readonly BackupRepository $repository = new BackupRepository()
    ) {
    }

    public function directory(): string
    {
        // Prefer a location outside the public web root.
        $external = dirname(ROOT_PATH) . '/' . Config::EXTERNAL_DIR . '/backups';
        if (is_dir($external) && is_writable($external)) {
            return $external;
        }
        $parent = dirname($external);
        if (is_dir($parent) && is_writable($parent) && (is_dir($external) || @mkdir($external, 0750, true))) {
            return $external;
        }

        $internal = STORAGE_PATH . '/backups';
        if (!is_dir($internal)) {
            @mkdir($internal, 0755, true);
        }
        return $internal;
    }

    // ------------------------------------------------------------------
    //  Creating backups
    // ------------------------------------------------------------------

    /**
     * @param string $type database|files|full|update
     * @return array{ok:bool,message:string,backup_id:int|null,path:string|null,size:int}
     */
    public function create(string $type = self::TYPE_DATABASE, string $note = ''): array
    {
        $type = in_array($type, [self::TYPE_DATABASE, self::TYPE_FILES, self::TYPE_FULL, self::TYPE_UPDATE], true)
            ? $type
            : self::TYPE_DATABASE;

        $stamp = date('Y-m-d-His');
        $name = $type . '-' . $stamp;
        $directory = $this->directory();

        if (!is_dir($directory) || !is_writable($directory)) {
            return [
                'ok'        => false,
                'message'   => 'The backup directory is not writable: ' . $directory,
                'backup_id' => null,
                'path'      => null,
                'size'      => 0,
            ];
        }

        $backupId = $this->repository->create([
            'name'        => $name,
            'type'        => $type,
            'status'      => 'running',
            'note'        => mb_substr($note, 0, 300),
            'app_version' => Version::current(),
            'created_by'  => Auth::id(),
            'started_at'  => Database::now(),
        ]);

        try {
            $result = match ($type) {
                self::TYPE_FILES => $this->archiveFiles($directory . '/' . $name . '.zip'),
                self::TYPE_FULL, self::TYPE_UPDATE => $this->createFull($directory, $name),
                default => $this->dumpDatabase($directory . '/' . $name . '.sql'),
            };

            $checksum = is_file($result['path']) ? (hash_file('sha256', $result['path']) ?: null) : null;

            $this->repository->update($backupId, [
                'status'       => 'completed',
                'path'         => $result['path'],
                'size'         => $result['size'],
                'table_count'  => $result['tables'] ?? 0,
                'file_count'   => $result['files'] ?? 0,
                'checksum'     => $checksum,
                'completed_at' => Database::now(),
            ]);

            $this->prune();

            AuditService::instance()->log('backup.create', 'backup', $backupId, $type . ' backup created');
            Logger::info('Backup created', [
                'type' => $type,
                'size' => $result['size'],
                'path' => basename($result['path']),
            ]);

            return [
                'ok'        => true,
                'message'   => ucfirst($type) . ' backup created (' . Str::bytesToHuman((float) $result['size']) . ').',
                'backup_id' => $backupId,
                'path'      => $result['path'],
                'size'      => $result['size'],
            ];
        } catch (\Throwable $e) {
            $this->repository->update($backupId, [
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'completed_at'  => Database::now(),
            ]);
            Logger::error('Backup failed: ' . $e->getMessage(), ['type' => $type]);

            return [
                'ok'        => false,
                'message'   => 'Backup failed: ' . $e->getMessage(),
                'backup_id' => $backupId,
                'path'      => null,
                'size'      => 0,
            ];
        }
    }

    /** @return array{path:string,size:int,tables:int,files:int} */
    private function createFull(string $directory, string $name): array
    {
        $sqlPath = $directory . '/' . $name . '.sql';
        $dump = $this->dumpDatabase($sqlPath);

        $zipPath = $directory . '/' . $name . '.zip';
        $archive = $this->archiveFiles($zipPath, $sqlPath);

        // The SQL now lives inside the archive.
        @unlink($sqlPath);

        return [
            'path'   => $archive['path'],
            'size'   => $archive['size'],
            'tables' => $dump['tables'],
            'files'  => $archive['files'],
        ];
    }

    /**
     * Write a restorable SQL dump using only PDO.
     *
     * @return array{path:string,size:int,tables:int,files:int}
     */
    public function dumpDatabase(string $path): array
    {
        $db = Database::instance();
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot write the dump file.');
        }

        $tables = $db->tables();
        $isSqlite = $db->isSqlite();

        fwrite($handle, "-- Shubh Kankotri database backup\n");
        fwrite($handle, '-- Generated: ' . date('c') . "\n");
        fwrite($handle, '-- Application version: ' . Version::current() . "\n");
        fwrite($handle, '-- Driver: ' . $db->driver() . ' ' . $db->version() . "\n");
        fwrite($handle, '-- Tables: ' . count($tables) . "\n\n");

        if (!$isSqlite) {
            fwrite($handle, "SET NAMES utf8mb4;\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
        } else {
            fwrite($handle, "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n\n");
        }

        foreach ($tables as $table) {
            $quoted = $db->wrap($table);

            fwrite($handle, "--\n-- Table: {$table}\n--\n");
            fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n");

            if ($isSqlite) {
                $create = (string) $db->value(
                    "SELECT sql FROM sqlite_master WHERE type='table' AND name = :name",
                    ['name' => $table],
                    ''
                );
                fwrite($handle, rtrim($create, ";\n") . ";\n\n");
            } else {
                $row = $db->first("SHOW CREATE TABLE {$quoted}");
                $create = (string) ($row['Create Table'] ?? '');
                fwrite($handle, $create . ";\n\n");
            }

            $this->dumpTableRows($handle, $table, $quoted);
        }

        if (!$isSqlite) {
            fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        } else {
            fwrite($handle, "\nCOMMIT;\nPRAGMA foreign_keys=ON;\n");
        }
        fwrite($handle, "-- End of backup\n");
        fclose($handle);

        return [
            'path'   => $path,
            'size'   => (int) filesize($path),
            'tables' => count($tables),
            'files'  => 0,
        ];
    }

    /**
     * Stream a table's rows in chunks so a large table cannot exhaust memory.
     *
     * @param resource $handle
     */
    private function dumpTableRows($handle, string $table, string $quoted): void
    {
        $db = Database::instance();
        $pdo = $db->pdo();

        $total = (int) $db->value("SELECT COUNT(*) FROM {$quoted}", [], 0);
        if ($total === 0) {
            return;
        }

        $chunkSize = 500;
        $offset = 0;

        while ($offset < $total) {
            $rows = $db->select("SELECT * FROM {$quoted} LIMIT {$chunkSize} OFFSET {$offset}");
            if ($rows === []) {
                break;
            }

            $columns = array_keys($rows[0]);
            $columnList = implode(', ', array_map([$db, 'wrap'], $columns));

            $values = [];
            foreach ($rows as $row) {
                $encoded = [];
                foreach ($columns as $column) {
                    $value = $row[$column];
                    if ($value === null) {
                        $encoded[] = 'NULL';
                        continue;
                    }
                    if (is_int($value) || is_float($value)) {
                        $encoded[] = (string) $value;
                        continue;
                    }
                    if (is_bool($value)) {
                        $encoded[] = $value ? '1' : '0';
                        continue;
                    }
                    // PDO::quote handles the driver's escaping rules.
                    $encoded[] = $pdo->quote((string) $value);
                }
                $values[] = '(' . implode(', ', $encoded) . ')';
            }

            fwrite(
                $handle,
                "INSERT INTO {$quoted} ({$columnList}) VALUES\n" . implode(",\n", $values) . ";\n"
            );

            $offset += $chunkSize;
        }
        fwrite($handle, "\n");
    }

    /**
     * ZIP the application files.
     *
     * @param string|null $extraFile an additional file to place at the root
     * @return array{path:string,size:int,files:int,tables:int}
     */
    public function archiveFiles(string $path, ?string $extraFile = null): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return $this->archiveFilesTar($path, $extraFile);
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create the ZIP archive.');
        }

        $count = 0;
        foreach ($this->collectFiles() as $absolute => $relative) {
            if ($zip->addFile($absolute, $relative)) {
                $count++;
            }
        }

        if ($extraFile !== null && is_file($extraFile)) {
            $zip->addFile($extraFile, 'database.sql');
            $count++;
        }

        $zip->setArchiveComment(
            'Shubh Kankotri backup ' . Version::current() . ' - ' . date('c')
        );
        $zip->close();

        return ['path' => $path, 'size' => (int) filesize($path), 'files' => $count, 'tables' => 0];
    }

    /** PharData fallback when the zip extension is missing. */
    private function archiveFilesTar(string $path, ?string $extraFile): array
    {
        if (!class_exists(\PharData::class)) {
            throw new \RuntimeException('Neither the zip extension nor PharData is available for file backups.');
        }
        $tarPath = preg_replace('/\.zip$/', '.tar', $path) ?? ($path . '.tar');
        @unlink($tarPath);

        $archive = new \PharData($tarPath);
        $count = 0;
        foreach ($this->collectFiles() as $absolute => $relative) {
            $archive->addFile($absolute, $relative);
            $count++;
        }
        if ($extraFile !== null && is_file($extraFile)) {
            $archive->addFile($extraFile, 'database.sql');
            $count++;
        }
        unset($archive);

        return ['path' => $tarPath, 'size' => (int) filesize($tarPath), 'files' => $count, 'tables' => 0];
    }

    /**
     * Files to include in an archive.
     *
     * @return array<string,string> absolute path => path inside the archive
     */
    private function collectFiles(): array
    {
        $root = ROOT_PATH;
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $absolute = $item->getPathname();
            $relative = ltrim(str_replace('\\', '/', substr($absolute, strlen($root))), '/');

            if ($relative === '' || $this->isExcluded($relative)) {
                continue;
            }
            if (!$item->isFile()) {
                continue;
            }
            // Skip anything unreadable rather than aborting the whole backup.
            if (!$item->isReadable()) {
                continue;
            }
            $files[$absolute] = $relative;
        }

        return $files;
    }

    private function isExcluded(string $relative): bool
    {
        foreach (self::EXCLUDED_DIRECTORIES as $excluded) {
            if ($relative === $excluded || str_starts_with($relative, $excluded . '/')) {
                return true;
            }
        }
        // Never archive another archive.
        return (bool) preg_match('/\.(zip|tar|gz|sql)$/i', $relative)
            && str_starts_with($relative, 'storage/');
    }

    // ------------------------------------------------------------------
    //  Restoring
    // ------------------------------------------------------------------

    /**
     * Restore a database dump.
     *
     * Statements are split on semicolons that are outside quotes, and each is
     * executed through PDO. Only DDL/DML that a dump can legitimately contain
     * is allowed through - nothing else is executed.
     *
     * @return array{ok:bool,message:string,statements:int}
     */
    public function restoreDatabase(string $sqlPath): array
    {
        if (!is_file($sqlPath) || !is_readable($sqlPath)) {
            return ['ok' => false, 'message' => 'The backup file could not be read.', 'statements' => 0];
        }

        $db = Database::instance();
        $pdo = $db->pdo();
        $executed = 0;

        $handle = fopen($sqlPath, 'rb');
        if ($handle === false) {
            return ['ok' => false, 'message' => 'The backup file could not be opened.', 'statements' => 0];
        }

        try {
            if ($db->isMysql()) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            }

            $buffer = '';
            while (($line = fgets($handle)) !== false) {
                $trimmed = ltrim($line);
                // Skip comments, but only when we are not mid-statement.
                if ($buffer === '' && ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*'))) {
                    continue;
                }
                $buffer .= $line;

                if (!$this->statementIsComplete($buffer)) {
                    continue;
                }

                $statement = trim($buffer);
                $buffer = '';
                $statement = rtrim($statement, ";\r\n\t ");
                if ($statement === '') {
                    continue;
                }
                if (!$this->isAllowedRestoreStatement($statement)) {
                    Logger::security('Rejected an unexpected statement while restoring a backup', [
                        'statement' => mb_substr($statement, 0, 120),
                    ]);
                    continue;
                }

                $pdo->exec($statement);
                $executed++;
            }

            if ($db->isMysql()) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        } catch (\Throwable $e) {
            fclose($handle);
            Logger::error('Database restore failed: ' . $e->getMessage());
            return [
                'ok'         => false,
                'message'    => 'Restore failed after ' . $executed . ' statement(s): ' . $e->getMessage(),
                'statements' => $executed,
            ];
        }

        fclose($handle);
        \App\Core\Cache::flush();
        SettingsService::instance()->flush();

        AuditService::instance()->log('backup.restore', 'backup', null, basename($sqlPath));
        Logger::info('Database restored', ['file' => basename($sqlPath), 'statements' => $executed]);

        return [
            'ok'         => true,
            'message'    => 'Database restored (' . $executed . ' statements).',
            'statements' => $executed,
        ];
    }

    /** Is the buffered text a complete statement (semicolon outside quotes)? */
    private function statementIsComplete(string $buffer): bool
    {
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $length = strlen($buffer);

        for ($i = 0; $i < $length; $i++) {
            $char = $buffer[$i];
            if ($char === '\\') {
                $i++; // skip the escaped character
                continue;
            }
            if ($char === "'" && !$inDouble && !$inBacktick) {
                $inSingle = !$inSingle;
                continue;
            }
            if ($char === '"' && !$inSingle && !$inBacktick) {
                $inDouble = !$inDouble;
                continue;
            }
            if ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
                continue;
            }
            if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whitelist of statement types a dump may contain.
     *
     * This is the guard that stops a tampered or untrusted .sql file from
     * doing anything other than rebuilding the schema and data.
     */
    private function isAllowedRestoreStatement(string $statement): bool
    {
        $normalised = strtoupper(ltrim($statement));
        $allowed = [
            'CREATE TABLE', 'DROP TABLE', 'INSERT INTO', 'ALTER TABLE',
            'CREATE INDEX', 'CREATE UNIQUE INDEX', 'DROP INDEX',
            'CREATE VIEW', 'DROP VIEW',
            'SET ', 'PRAGMA ', 'BEGIN', 'COMMIT', 'START TRANSACTION',
            'LOCK TABLES', 'UNLOCK TABLES', 'TRUNCATE TABLE', 'REPLACE INTO',
            'DELETE FROM', 'UPDATE ',
        ];
        foreach ($allowed as $prefix) {
            if (str_starts_with($normalised, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Restore application files from an archive.
     *
     * Protected paths are never overwritten, and every entry is checked for
     * path traversal (Zip Slip) before extraction.
     *
     * @return array{ok:bool,message:string,files:int}
     */
    public function restoreFiles(string $archivePath, array $protectedPaths = []): array
    {
        if (!is_file($archivePath)) {
            return ['ok' => false, 'message' => 'The archive could not be found.', 'files' => 0];
        }
        if (!class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'message' => 'The zip extension is required to restore files.', 'files' => 0];
        }

        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            return ['ok' => false, 'message' => 'The archive could not be opened.', 'files' => 0];
        }

        $protectedPaths = $protectedPaths !== [] ? $protectedPaths : (array) Config::get('update.protected_paths', []);
        $restored = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false || str_ends_with($entry, '/')) {
                continue;
            }
            if ($entry === 'database.sql') {
                continue; // handled by restoreDatabase()
            }
            $safe = ArchiveExtractor::safeRelativePath($entry);
            if ($safe === null) {
                Logger::security('Blocked a traversal path while restoring files', ['entry' => $entry]);
                continue;
            }
            if (ArchiveExtractor::isProtected($safe, $protectedPaths)) {
                continue;
            }

            $destination = ROOT_PATH . '/' . $safe;
            $directory = dirname($destination);
            if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                continue;
            }
            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                continue;
            }
            if (@file_put_contents($destination, $contents) !== false) {
                $restored++;
            }
        }
        $zip->close();

        \App\Core\Cache::flush();
        AuditService::instance()->log('backup.restore_files', 'backup', null, basename($archivePath));

        return ['ok' => true, 'message' => $restored . ' file(s) restored.', 'files' => $restored];
    }

    /** Extract database.sql from a full backup so it can be restored. */
    public function extractDatabaseDump(string $archivePath): ?string
    {
        if (!class_exists(\ZipArchive::class)) {
            return null;
        }
        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            return null;
        }
        $contents = $zip->getFromName('database.sql');
        $zip->close();
        if ($contents === false) {
            return null;
        }
        $target = STORAGE_PATH . '/tmp/restore-' . bin2hex(random_bytes(6)) . '.sql';
        if (!is_dir(dirname($target))) {
            @mkdir(dirname($target), 0755, true);
        }
        return @file_put_contents($target, $contents) === false ? null : $target;
    }

    // ------------------------------------------------------------------
    //  Housekeeping
    // ------------------------------------------------------------------

    /** Remove backups beyond the configured keep limit. */
    public function prune(): int
    {
        $keep = max(1, (int) Config::get('update.keep_backups', 5));
        $removed = 0;
        foreach ($this->repository->beyondKeepLimit($keep) as $backup) {
            if ($this->deleteFile((string) ($backup['path'] ?? ''))) {
                $removed++;
            }
            $this->repository->forceDelete((int) $backup['id']);
        }
        if ($removed > 0) {
            Logger::info('Pruned old backups', ['removed' => $removed]);
        }
        return $removed;
    }

    public function delete(int $backupId): array
    {
        $backup = $this->repository->find($backupId);
        if ($backup === null) {
            return ['ok' => false, 'message' => 'That backup no longer exists.'];
        }
        $this->deleteFile((string) ($backup['path'] ?? ''));
        $this->repository->forceDelete($backupId);

        AuditService::instance()->log('backup.delete', 'backup', $backupId, (string) $backup['name']);
        return ['ok' => true, 'message' => 'Backup deleted.'];
    }

    /** Delete a backup file, refusing anything outside the backup directory. */
    private function deleteFile(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            return false;
        }
        $allowed = [realpath($this->directory()), realpath(STORAGE_PATH . '/backups')];
        foreach (array_filter($allowed) as $root) {
            if (str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                return @unlink($real);
            }
        }
        Logger::security('Refused to delete a file outside the backup directory', ['path' => $path]);
        return false;
    }

    /** Absolute path of a backup, validated for download. */
    public function pathForDownload(int $backupId): ?array
    {
        $backup = $this->repository->find($backupId);
        if ($backup === null || (string) $backup['status'] !== 'completed') {
            return null;
        }
        $path = (string) ($backup['path'] ?? '');
        $real = $path === '' ? false : realpath($path);
        if ($real === false || !is_file($real)) {
            return null;
        }
        $allowed = array_filter([realpath($this->directory()), realpath(STORAGE_PATH . '/backups')]);
        foreach ($allowed as $root) {
            if (str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                return [
                    'path'     => $real,
                    'filename' => basename($real),
                    'mime'     => str_ends_with($real, '.sql') ? 'application/sql' : 'application/zip',
                    'size'     => (int) filesize($real),
                ];
            }
        }
        return null;
    }

    /** Verify a stored checksum still matches the file on disk. */
    public function verify(int $backupId): array
    {
        $backup = $this->repository->find($backupId);
        if ($backup === null) {
            return ['ok' => false, 'message' => 'Backup not found.'];
        }
        $path = (string) ($backup['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            return ['ok' => false, 'message' => 'The backup file is missing from disk.'];
        }
        $expected = (string) ($backup['checksum'] ?? '');
        if ($expected === '') {
            return ['ok' => true, 'message' => 'The file exists; no checksum was recorded.'];
        }
        $actual = hash_file('sha256', $path);
        return $actual === $expected
            ? ['ok' => true, 'message' => 'Checksum matches.']
            : ['ok' => false, 'message' => 'Checksum mismatch - the file may be corrupt.'];
    }

    public function stats(): array
    {
        $latest = $this->repository->latestCompleted();
        return [
            'count'      => $this->repository->count(),
            'total_size' => $this->repository->totalSize(),
            'latest'     => $latest,
            'directory'  => $this->directory(),
            'outside_web_root' => !str_starts_with($this->directory(), ROOT_PATH . DIRECTORY_SEPARATOR),
        ];
    }
}
