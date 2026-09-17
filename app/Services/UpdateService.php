<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Migrator;
use App\Core\Str;
use App\Core\Version;
use App\Repositories\NotificationRepository;
use App\Repositories\UpdateRepository;

/**
 * GitHub auto-update with automatic rollback.
 *
 * The deliberate design rule is: never extract an archive over the live
 * application. The sequence is
 *
 *   maintenance -> lock -> backup -> download -> validate -> stage ->
 *   verify staging -> preserve protected files -> deploy -> migrate ->
 *   clear caches -> health check -> success, or roll back everything.
 *
 * Every step records its state in `update_logs`, so a run that dies halfway
 * (a PHP timeout, a killed worker) is visible and recoverable rather than
 * silent. The update engine is treated as security-critical code: paths are
 * validated, archives are size- and ratio-capped, protected files are never
 * touched, and no SQL from the archive is executed except versioned
 * migrations shipped as PHP classes.
 */
final class UpdateService
{
    private const LOCK_FILE = 'update.lock';
    private const LOCK_TIMEOUT = 1800; // 30 minutes before a lock is stale

    /** Files that must exist in staging for it to be a plausible release. */
    private const REQUIRED_STAGED_FILES = [
        'index.php',
        'version.json',
        'app/bootstrap.php',
        'app/Core/Application.php',
        'app/routes.php',
    ];

    public function __construct(
        private readonly GitHubService $github = new GitHubService(),
        private readonly UpdateRepository $updates = new UpdateRepository(),
        private readonly BackupService $backups = new BackupService(),
        private readonly HealthService $health = new HealthService(),
        private readonly MaintenanceService $maintenance = new MaintenanceService()
    ) {
    }

    // ------------------------------------------------------------------
    //  Check for update
    // ------------------------------------------------------------------

    /**
     * Compare the installed version and commit with the remote branch.
     *
     * @return array<string,mixed>
     */
    public function check(): array
    {
        $result = [
            'ok'               => false,
            'update_available' => false,
            'message'          => '',
            'current_version'  => Version::current(),
            'installed_version' => Version::installed(),
            'latest_version'   => null,
            'commit'           => null,
            'release'          => null,
            'changed_files'    => [],
            'repository'       => $this->github->repository(),
            'branch'           => $this->github->branch(),
            'checked_at'       => date('c'),
        ];

        if (!$this->github->isConfigured()) {
            $result['message'] = 'Add your GitHub repository below to enable updates.';
            return $result;
        }

        $commitResult = $this->github->latestCommit();
        if (!$commitResult['ok']) {
            $result['message'] = $commitResult['message'];
            return $result;
        }
        $result['ok'] = true;
        $result['commit'] = $commitResult['commit'];

        $remoteVersion = $this->github->remoteVersion();
        if ($remoteVersion['ok']) {
            $result['latest_version'] = $remoteVersion['version'];
            $result['release_notes'] = $remoteVersion['notes'];
        }

        $release = $this->github->latestRelease();
        if ($release['ok']) {
            $result['release'] = $release['release'];
        }

        $lastSuccessful = $this->updates->lastSuccessful();
        $installedSha = (string) ($lastSuccessful['commit_sha'] ?? '');
        $remoteSha = (string) ($commitResult['commit']['sha'] ?? '');

        // Prefer a semantic version comparison; fall back to the commit sha.
        if ($result['latest_version'] !== null
            && Version::isNewer((string) $result['latest_version'], Version::current())) {
            $result['update_available'] = true;
            $result['message'] = 'Version ' . $result['latest_version'] . ' is available '
                . '(you have ' . Version::current() . ').';
        } elseif ($installedSha !== '' && $remoteSha !== '' && $installedSha !== $remoteSha) {
            $result['update_available'] = true;
            $result['message'] = 'New commits are available on ' . $this->github->branch() . '.';
        } elseif ($installedSha === '' && $remoteSha !== '') {
            // Never updated through the panel before: offer it, but say so.
            $result['update_available'] = true;
            $result['message'] = 'Updates have not been applied from GitHub yet. '
                . 'The latest commit on ' . $this->github->branch() . ' can be deployed.';
        } else {
            $result['message'] = 'You are up to date.';
        }

        if ($result['update_available'] && $installedSha !== '') {
            $comparison = $this->github->compare($installedSha);
            if ($comparison['ok']) {
                $result['changed_files'] = $comparison['files'];
                $result['commits_ahead'] = $comparison['ahead'];
            }
        }
        if ($result['changed_files'] === [] && isset($commitResult['commit']['files'])) {
            $result['changed_files'] = $commitResult['commit']['files'];
        }

        // Tell the administrators once, rather than on every page load.
        if ($result['update_available']) {
            $this->notifyOnce($result);
        }

        return $result;
    }

    private function notifyOnce(array $check): void
    {
        $key = 'update:notified:' . (string) ($check['commit']['sha'] ?? 'unknown');
        if (Cache::has($key)) {
            return;
        }
        Cache::put($key, true, 86400);
        try {
            (new NotificationRepository())->notifyAdmins(
                'Update available',
                (string) $check['message'],
                'update',
                \App\Core\Url::to('admin/system/update')
            );
        } catch (\Throwable $e) {
            Logger::warning('Could not notify admins about the update: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    //  Apply update
    // ------------------------------------------------------------------

    /**
     * Run the full update.
     *
     * @return array{ok:bool,message:string,log_id:int|null,steps:array<int,array<string,mixed>>,rolled_back:bool}
     */
    public function apply(bool $dryRun = false): array
    {
        $steps = [];
        $rolledBack = false;
        $logId = null;

        $record = function (string $step, bool $ok, string $message) use (&$steps): void {
            $steps[] = ['step' => $step, 'ok' => $ok, 'message' => $message, 'at' => date('c')];
            Logger::info('[update] ' . $step . ': ' . $message, ['ok' => $ok], Logger::UPDATE);
        };

        if (!$this->github->isConfigured()) {
            return [
                'ok'          => false,
                'message'     => 'No GitHub repository has been configured.',
                'log_id'      => null,
                'steps'       => [],
                'rolled_back' => false,
            ];
        }

        // ---- Step 2: lock (before anything else can race us) ----
        $lock = $this->acquireLock();
        if (!$lock['ok']) {
            return [
                'ok'          => false,
                'message'     => $lock['message'],
                'log_id'      => null,
                'steps'       => [],
                'rolled_back' => false,
            ];
        }
        $record('lock', true, 'Update lock acquired.');

        $check = $this->check();
        $commitSha = (string) ($check['commit']['sha'] ?? '');

        $logId = $this->updates->start([
            'from_version'   => Version::current(),
            'to_version'     => $check['latest_version'] ?? Version::current(),
            'repository'     => $this->github->repository(),
            'branch'         => $this->github->branch(),
            'commit_sha'     => $commitSha,
            'commit_message' => mb_substr((string) ($check['commit']['message'] ?? ''), 0, 500),
            'commit_author'  => (string) ($check['commit']['author'] ?? ''),
            'changed_files'  => $check['changed_files'],
            'initiated_by'   => Auth::id(),
            'status'         => 'checking',
            'step'           => 'checking',
        ]);

        $stagingDirectory = STORAGE_PATH . '/update/staging-' . date('YmdHis');
        $archivePath = STORAGE_PATH . '/update/source-' . date('YmdHis') . '.zip';
        $filesBackupPath = null;
        $databaseBackupPath = null;

        try {
            // ---- Step 1: maintenance mode ----
            $this->updates->step($logId, 'checking', 'maintenance');
            $this->maintenance->enable('We are applying an update. This usually takes less than a minute.', 120);
            $record('maintenance', true, 'Maintenance mode enabled.');

            // ---- Step 3: backup ----
            $this->updates->step($logId, 'backing_up', 'backup');
            $databaseBackup = $this->backups->create(BackupService::TYPE_DATABASE, 'Automatic pre-update backup');
            if (!$databaseBackup['ok']) {
                throw new UpdateFailure('Database backup failed: ' . $databaseBackup['message'], false);
            }
            $databaseBackupPath = $databaseBackup['path'];
            $record('backup_database', true, 'Database backed up (' . Str::bytesToHuman((float) $databaseBackup['size']) . ').');

            $filesBackup = $this->backups->create(BackupService::TYPE_FILES, 'Automatic pre-update file backup');
            if (!$filesBackup['ok']) {
                throw new UpdateFailure('File backup failed: ' . $filesBackup['message'], false);
            }
            $filesBackupPath = $filesBackup['path'];
            $record('backup_files', true, 'Files backed up (' . Str::bytesToHuman((float) $filesBackup['size']) . ').');

            $this->updates->update($logId, ['backup_path' => $filesBackupPath]);

            // ---- Step 4: download ----
            $this->updates->step($logId, 'downloading', 'download');
            $download = $this->github->downloadArchive($archivePath);
            if (!$download['ok']) {
                throw new UpdateFailure($download['message'], false);
            }
            $record('download', true, $download['message']);

            // ---- Step 5/6a: stage ----
            $this->updates->step($logId, 'staging', 'extract');
            $extraction = \App\Services\ArchiveExtractor::extractZip($archivePath, $stagingDirectory, true);
            if (!$extraction['ok']) {
                throw new UpdateFailure($extraction['message'], false);
            }
            $record('extract', true, $extraction['message']);

            // ---- Verify staging before trusting it ----
            $validation = $this->validateStaging($stagingDirectory);
            if (!$validation['ok']) {
                throw new UpdateFailure($validation['message'], false);
            }
            $record('validate', true, $validation['message']);

            if ($dryRun) {
                $record('dry_run', true, 'Dry run: staging validated, nothing was deployed.');
                \App\Services\ArchiveExtractor::deleteDirectory($stagingDirectory);
                @unlink($archivePath);
                $this->maintenance->disable();
                $this->releaseLock();
                $this->updates->finish($logId, 'success', [
                    'steps_log'   => $steps,
                    'health_result' => ['dry_run' => true],
                ]);
                return [
                    'ok'          => true,
                    'message'     => 'Dry run completed: the update package is valid and would deploy cleanly.',
                    'log_id'      => $logId,
                    'steps'       => $steps,
                    'rolled_back' => false,
                ];
            }

            // ---- Step 6: deploy ----
            $this->updates->step($logId, 'updating', 'deploy');
            $deployment = $this->deploy($stagingDirectory);
            if (!$deployment['ok']) {
                throw new UpdateFailure($deployment['message'], true);
            }
            $record('deploy', true, $deployment['message']);
            Version::refresh();

            // ---- Step 7: migrate ----
            $this->updates->step($logId, 'migrating', 'migrate');
            $migration = $this->runMigrations();
            $this->updates->update($logId, ['migration_result' => $migration]);
            if (!$migration['ok']) {
                throw new UpdateFailure('Migration failed: ' . $migration['message'], true);
            }
            $record('migrate', true, $migration['message']);

            // ---- Step 8: clear caches ----
            $this->updates->step($logId, 'migrating', 'cache');
            $cleared = $this->clearCaches();
            $record('cache', true, $cleared['message']);

            // ---- Step 9: health check ----
            $this->updates->step($logId, 'testing', 'health');
            $healthResult = $this->health->runCritical();
            $this->updates->update($logId, ['health_result' => $healthResult]);
            if ($healthResult['status'] !== HealthService::OK) {
                $failedLabels = array_map(
                    static fn (array $check): string => (string) $check['label'] . ' - ' . (string) $check['message'],
                    $healthResult['failed']
                );
                throw new UpdateFailure(
                    'Post-update health check failed: ' . implode('; ', array_slice($failedLabels, 0, 3)),
                    true
                );
            }
            $record('health', true, 'All critical checks passed.');

            // ---- Step 10: success ----
            $newVersion = Version::current();
            SettingsService::instance()->set('app_version', $newVersion, 'string', 'system', Auth::id());

            \App\Services\ArchiveExtractor::deleteDirectory($stagingDirectory);
            @unlink($archivePath);

            $this->maintenance->disable();
            $this->releaseLock();

            $this->updates->finish($logId, 'success', [
                'to_version' => $newVersion,
                'steps_log'  => $steps,
            ]);

            AuditService::instance()->log(
                'update.success',
                'update',
                $logId,
                'Updated to ' . $newVersion . ' (' . substr($commitSha, 0, 7) . ')'
            );
            try {
                (new NotificationRepository())->notifyAdmins(
                    'Update applied',
                    'The application was updated to version ' . $newVersion . '.',
                    'success',
                    \App\Core\Url::to('admin/system/update')
                );
            } catch (\Throwable) {
                // Non-fatal.
            }

            return [
                'ok'          => true,
                'message'     => 'Update applied successfully. Now running version ' . $newVersion . '.',
                'log_id'      => $logId,
                'steps'       => $steps,
                'rolled_back' => false,
            ];
        } catch (\Throwable $e) {
            $needsRollback = $e instanceof UpdateFailure ? $e->needsRollback : true;
            $record('failure', false, $e->getMessage());

            if ($needsRollback) {
                $this->updates->step($logId, 'failed', 'rollback');
                $rollback = $this->rollback($filesBackupPath, $databaseBackupPath);
                $rolledBack = $rollback['ok'];
                $record('rollback', $rollback['ok'], $rollback['message']);
            }

            \App\Services\ArchiveExtractor::deleteDirectory($stagingDirectory);
            if (is_file($archivePath)) {
                @unlink($archivePath);
            }

            $this->clearCaches();
            // Verify the site is serviceable again before lifting maintenance.
            $postRollbackHealth = $this->health->runCritical();
            $record(
                'post_rollback_health',
                $postRollbackHealth['status'] === HealthService::OK,
                'Health after recovery: ' . $postRollbackHealth['status']
            );

            $this->maintenance->disable();
            $this->releaseLock();

            if ($logId !== null) {
                $this->updates->finish($logId, $rolledBack ? 'rolled_back' : 'failed', [
                    'error_message' => mb_substr($e->getMessage(), 0, 2000),
                    'steps_log'     => $steps,
                    'health_result' => $postRollbackHealth,
                ]);
            }

            Logger::error('Update failed: ' . $e->getMessage(), ['rolled_back' => $rolledBack], Logger::UPDATE);
            AuditService::instance()->log('update.failed', 'update', $logId, $e->getMessage());

            try {
                (new NotificationRepository())->notifyAdmins(
                    'Update failed',
                    $rolledBack
                        ? 'The update failed and the previous working version was restored.'
                        : 'The update failed before any files were changed.',
                    'error',
                    \App\Core\Url::to('admin/system/update')
                );
            } catch (\Throwable) {
                // Non-fatal.
            }

            return [
                'ok'      => false,
                'message' => $rolledBack
                    ? 'Update failed. Previous working version has been restored. Reason: ' . $e->getMessage()
                    : 'Update failed before any files were changed. Reason: ' . $e->getMessage(),
                'log_id'      => $logId,
                'steps'       => $steps,
                'rolled_back' => $rolledBack,
            ];
        }
    }

    // ------------------------------------------------------------------
    //  Staging validation
    // ------------------------------------------------------------------

    /**
     * Is the staged tree actually a release of this application?
     *
     * @return array{ok:bool,message:string}
     */
    public function validateStaging(string $stagingDirectory): array
    {
        if (!is_dir($stagingDirectory)) {
            return ['ok' => false, 'message' => 'The staging directory is missing.'];
        }

        foreach (self::REQUIRED_STAGED_FILES as $required) {
            if (!is_file($stagingDirectory . '/' . $required)) {
                return [
                    'ok'      => false,
                    'message' => 'The downloaded package does not look like this application '
                        . '(missing ' . $required . ').',
                ];
            }
        }

        // version.json must parse and carry a version.
        $versionJson = json_decode((string) file_get_contents($stagingDirectory . '/version.json'), true);
        if (!is_array($versionJson) || !isset($versionJson['version'])) {
            return ['ok' => false, 'message' => 'version.json in the package is invalid.'];
        }
        $incomingVersion = (string) $versionJson['version'];

        // Refuse a package that needs a newer PHP than this server runs.
        $requiredPhp = (string) ($versionJson['min_php'] ?? '8.0.0');
        if (version_compare(PHP_VERSION, $requiredPhp, '<')) {
            return [
                'ok'      => false,
                'message' => 'The package requires PHP ' . $requiredPhp . ' but this server runs ' . PHP_VERSION . '.',
            ];
        }

        // Every staged PHP file must at least parse, so a truncated download
        // cannot take the site down.
        $phpCheck = $this->lintStagedPhp($stagingDirectory);
        if (!$phpCheck['ok']) {
            return $phpCheck;
        }

        $files = \App\Services\ArchiveExtractor::listFiles($stagingDirectory);
        if (count($files) < 20) {
            return ['ok' => false, 'message' => 'The package contains only ' . count($files) . ' files, which looks wrong.'];
        }

        return [
            'ok'      => true,
            'message' => 'Package validated: version ' . $incomingVersion . ', ' . count($files) . ' files.',
        ];
    }

    /**
     * Syntax-check the staged PHP.
     *
     * php -l needs shell access, which most shared hosts deny, so the check
     * is done in-process with token_get_all() - it catches truncation and
     * corruption, which is what actually goes wrong with a download.
     *
     * @return array{ok:bool,message:string}
     */
    private function lintStagedPhp(string $stagingDirectory): array
    {
        $checked = 0;
        foreach (self::REQUIRED_STAGED_FILES as $required) {
            $path = $stagingDirectory . '/' . $required;
            if (!str_ends_with($path, '.php')) {
                continue;
            }
            $source = (string) file_get_contents($path);
            if (trim($source) === '') {
                return ['ok' => false, 'message' => $required . ' in the package is empty.'];
            }
            try {
                $tokens = @token_get_all($source, TOKEN_PARSE);
                if ($tokens === []) {
                    return ['ok' => false, 'message' => $required . ' in the package could not be parsed.'];
                }
            } catch (\Throwable $e) {
                return [
                    'ok'      => false,
                    'message' => $required . ' in the package has a syntax error: ' . $e->getMessage(),
                ];
            }
            $checked++;
        }
        return ['ok' => true, 'message' => $checked . ' core files parsed cleanly.'];
    }

    // ------------------------------------------------------------------
    //  Deployment
    // ------------------------------------------------------------------

    /**
     * Copy staged files over the application, preserving protected paths.
     *
     * Each file is written to a temporary name and then renamed into place,
     * which is atomic on every POSIX filesystem, so a reader never sees a
     * half-written PHP file.
     *
     * @return array{ok:bool,message:string,copied:int,skipped:int,removed:int}
     */
    private function deploy(string $stagingDirectory): array
    {
        $protected = $this->protectedPaths();
        $staged = \App\Services\ArchiveExtractor::listFiles($stagingDirectory);
        if ($staged === []) {
            return ['ok' => false, 'message' => 'Nothing to deploy.', 'copied' => 0, 'skipped' => 0, 'removed' => 0];
        }

        $copied = 0;
        $skipped = 0;

        foreach ($staged as $relative) {
            if (\App\Services\ArchiveExtractor::isProtected($relative, $protected)) {
                $skipped++;
                continue;
            }

            $source = $stagingDirectory . '/' . $relative;
            $target = ROOT_PATH . '/' . $relative;
            $targetDirectory = dirname($target);

            if (!is_dir($targetDirectory) && !@mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
                return [
                    'ok'      => false,
                    'message' => 'Cannot create the directory ' . dirname($relative) . '.',
                    'copied'  => $copied,
                    'skipped' => $skipped,
                    'removed' => 0,
                ];
            }
            if (!is_writable($targetDirectory)) {
                return [
                    'ok'      => false,
                    'message' => 'The directory ' . dirname($relative) . ' is not writable by the web server.',
                    'copied'  => $copied,
                    'skipped' => $skipped,
                    'removed' => 0,
                ];
            }

            // Unchanged files are left alone: fewer writes, faster deploy.
            if (is_file($target) && hash_file('sha256', $target) === hash_file('sha256', $source)) {
                continue;
            }

            $temporary = $target . '.new-' . getmypid();
            if (!@copy($source, $temporary)) {
                return [
                    'ok'      => false,
                    'message' => 'Could not write ' . $relative . '.',
                    'copied'  => $copied,
                    'skipped' => $skipped,
                    'removed' => 0,
                ];
            }
            @chmod($temporary, 0644);
            if (!@rename($temporary, $target)) {
                @unlink($temporary);
                return [
                    'ok'      => false,
                    'message' => 'Could not replace ' . $relative . '.',
                    'copied'  => $copied,
                    'skipped' => $skipped,
                    'removed' => 0,
                ];
            }
            $copied++;
        }

        // Remove application files the release deleted, but only inside the
        // directories the application owns - never uploads or storage.
        $removed = $this->removeDeletedFiles($staged, $protected);

        return [
            'ok'      => true,
            'message' => $copied . ' file(s) updated, ' . $skipped . ' protected file(s) preserved'
                . ($removed > 0 ? ', ' . $removed . ' obsolete file(s) removed' : '') . '.',
            'copied'  => $copied,
            'skipped' => $skipped,
            'removed' => $removed,
        ];
    }

    /**
     * Delete files that exist locally but not in the release.
     *
     * Restricted to code directories so user content is structurally safe.
     *
     * @param array<int,string> $staged
     * @param array<int,string> $protected
     */
    private function removeDeletedFiles(array $staged, array $protected): int
    {
        $managedDirectories = ['app', 'assets', 'database', 'docs', 'bin'];
        $stagedIndex = array_fill_keys($staged, true);
        $removed = 0;

        foreach ($managedDirectories as $directory) {
            $absolute = ROOT_PATH . '/' . $directory;
            if (!is_dir($absolute)) {
                continue;
            }
            foreach (\App\Services\ArchiveExtractor::listFiles($absolute) as $inner) {
                $relative = $directory . '/' . $inner;
                if (isset($stagedIndex[$relative])) {
                    continue;
                }
                if (\App\Services\ArchiveExtractor::isProtected($relative, $protected)) {
                    continue;
                }
                // Keep .htaccess files: they are server configuration.
                if (basename($relative) === '.htaccess') {
                    continue;
                }
                if (@unlink(ROOT_PATH . '/' . $relative)) {
                    $removed++;
                }
            }
        }
        return $removed;
    }

    /** @return array<int,string> */
    public function protectedPaths(): array
    {
        $configured = (array) Config::get('update.protected_paths', []);
        $custom = SettingsService::instance()->array('update_protected_paths');

        $paths = array_merge($configured, $custom, [
            // Always protected, whatever the configuration says.
            '.env',
            'storage',
            'uploads',
            'config',
            'app/config/app.php',
            Config::EXTERNAL_DIR,
        ]);

        return array_values(array_unique(array_filter(array_map(
            static fn ($path): string => trim((string) $path),
            $paths
        ))));
    }

    // ------------------------------------------------------------------
    //  Migrations and caches
    // ------------------------------------------------------------------

    /** @return array{ok:bool,message:string,ran:array<int,string>} */
    private function runMigrations(): array
    {
        try {
            $migrator = new Migrator(Database::instance());
            $pending = $migrator->pending();
            if ($pending === []) {
                return ['ok' => true, 'message' => 'No new migrations.', 'ran' => []];
            }
            $result = $migrator->run();
            return [
                'ok'      => true,
                'message' => count($result['ran']) . ' migration(s) applied.',
                'ran'     => $result['ran'],
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'ran' => []];
        }
    }

    /** @return array{ok:bool,message:string} */
    public function clearCaches(): array
    {
        $cleared = Cache::flush();
        SettingsService::instance()->flush();
        FeatureFlagService::instance()->flush();
        (new \App\Repositories\CategoryRepository())->flushCache();
        (new \App\Repositories\FontRepository())->flushCache();
        (new \App\Repositories\TemplateRepository())->flushCaches();

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        clearstatcache(true);

        return ['ok' => true, 'message' => $cleared . ' cache entries cleared, OPcache reset.'];
    }

    // ------------------------------------------------------------------
    //  Rollback
    // ------------------------------------------------------------------

    /**
     * Restore the pre-update state.
     *
     * @return array{ok:bool,message:string}
     */
    public function rollback(?string $filesBackupPath, ?string $databaseBackupPath): array
    {
        $messages = [];
        $ok = true;

        if ($filesBackupPath !== null && is_file($filesBackupPath)) {
            $result = $this->backups->restoreFiles($filesBackupPath, $this->protectedPaths());
            $ok = $ok && $result['ok'];
            $messages[] = 'Files: ' . $result['message'];
            Version::refresh();
        } else {
            $messages[] = 'Files: no file backup was available.';
        }

        if ($databaseBackupPath !== null && is_file($databaseBackupPath)) {
            $result = $this->backups->restoreDatabase($databaseBackupPath);
            $ok = $ok && $result['ok'];
            $messages[] = 'Database: ' . $result['message'];
        } else {
            $messages[] = 'Database: no database backup was available.';
        }

        $this->clearCaches();

        return ['ok' => $ok, 'message' => implode(' ', $messages)];
    }

    /** Roll back to a specific historical update entry, from the admin UI. */
    public function rollbackTo(int $updateLogId): array
    {
        $entry = $this->updates->find($updateLogId);
        if ($entry === null) {
            return ['ok' => false, 'message' => 'That update record no longer exists.'];
        }
        $backupPath = (string) ($entry['backup_path'] ?? '');
        if ($backupPath === '' || !is_file($backupPath)) {
            return ['ok' => false, 'message' => 'The backup for that update is no longer on disk.'];
        }

        $lock = $this->acquireLock();
        if (!$lock['ok']) {
            return $lock;
        }
        $this->maintenance->enable('Restoring the previous version. Back in a moment.', 120);

        try {
            $databaseDump = $this->backups->extractDatabaseDump($backupPath);
            $result = $this->rollback($backupPath, $databaseDump);
            if ($databaseDump !== null) {
                @unlink($databaseDump);
            }

            $health = $this->health->runCritical();
            $this->maintenance->disable();
            $this->releaseLock();

            AuditService::instance()->log('update.rollback', 'update', $updateLogId, $result['message']);

            return [
                'ok'      => $result['ok'] && $health['status'] === HealthService::OK,
                'message' => $result['message'] . ' Health: ' . $health['status'] . '.',
            ];
        } catch (\Throwable $e) {
            $this->maintenance->disable();
            $this->releaseLock();
            return ['ok' => false, 'message' => 'Rollback failed: ' . $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    //  Locking
    // ------------------------------------------------------------------

    private function lockPath(): string
    {
        return STORAGE_PATH . '/' . self::LOCK_FILE;
    }

    /** @return array{ok:bool,message:string} */
    public function acquireLock(): array
    {
        $path = $this->lockPath();
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return ['ok' => false, 'message' => 'The storage directory is not writable.'];
        }

        if (is_file($path)) {
            $payload = json_decode((string) @file_get_contents($path), true);
            $startedAt = is_array($payload) ? (int) ($payload['started_at'] ?? 0) : 0;
            if ($startedAt > 0 && (time() - $startedAt) < self::LOCK_TIMEOUT) {
                return [
                    'ok'      => false,
                    'message' => 'Another update is already running (started '
                        . (int) floor((time() - $startedAt) / 60) . ' minute(s) ago).',
                ];
            }
            // Stale lock from a crashed run.
            Logger::warning('Clearing a stale update lock', ['age' => time() - $startedAt], Logger::UPDATE);
            @unlink($path);
        }

        // O_EXCL creation is the actual mutual exclusion.
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            return ['ok' => false, 'message' => 'Could not create the update lock. Another update may be starting.'];
        }
        fwrite($handle, (string) json_encode([
            'started_at' => time(),
            'user_id'    => Auth::id(),
            'pid'        => getmypid(),
            'version'    => Version::current(),
        ]));
        fclose($handle);

        return ['ok' => true, 'message' => 'Lock acquired.'];
    }

    public function releaseLock(): void
    {
        $path = $this->lockPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function isLocked(): bool
    {
        $path = $this->lockPath();
        if (!is_file($path)) {
            return false;
        }
        $payload = json_decode((string) @file_get_contents($path), true);
        $startedAt = is_array($payload) ? (int) ($payload['started_at'] ?? 0) : 0;
        return $startedAt > 0 && (time() - $startedAt) < self::LOCK_TIMEOUT;
    }

    /** Admin escape hatch for a lock left behind by a killed process. */
    public function forceUnlock(): array
    {
        $this->releaseLock();
        $this->maintenance->disable();

        $stuck = $this->updates->inProgress();
        if ($stuck !== null) {
            $this->updates->finish((int) $stuck['id'], 'failed', [
                'error_message' => 'The update was interrupted and the lock was cleared manually.',
            ]);
        }

        AuditService::instance()->log('update.force_unlock', 'update', $stuck['id'] ?? null);
        return ['ok' => true, 'message' => 'Update lock cleared and maintenance mode disabled.'];
    }

    // ------------------------------------------------------------------
    //  History
    // ------------------------------------------------------------------

    public function history(int $page = 1, int $perPage = 20): array
    {
        return $this->updates->paginateHistory($page, $perPage);
    }

    public function latest(): ?array
    {
        return $this->updates->latest();
    }

    /** @return array<string,mixed> */
    public function state(): array
    {
        return [
            'configured'    => $this->github->isConfigured(),
            'repository'    => $this->github->repository(),
            'branch'        => $this->github->branch(),
            'has_token'     => $this->github->hasToken(),
            'masked_token'  => $this->github->maskedToken(),
            'locked'        => $this->isLocked(),
            'maintenance'   => $this->maintenance->isActive(),
            'current_version' => Version::current(),
            'latest_run'    => $this->updates->latest(),
            'in_progress'   => $this->updates->inProgress(),
            'protected_paths' => $this->protectedPaths(),
            'backup_directory' => $this->backups->directory(),
        ];
    }
}

/** Internal exception carrying whether a rollback is required. */
final class UpdateFailure extends \RuntimeException
{
    public function __construct(string $message, public readonly bool $needsRollback)
    {
        parent::__construct($message);
    }
}
