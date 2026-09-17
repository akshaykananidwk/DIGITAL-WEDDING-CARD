<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * Safe archive extraction.
 *
 * The update system never extracts an archive over the live application. It
 * extracts into a staging directory, validates it, and only then copies files
 * across. Even so, every entry goes through safeRelativePath() first, because
 * Zip Slip is exactly the kind of bug that turns an update feature into
 * remote code execution.
 */
final class ArchiveExtractor
{
    /** Entry types that are never written, whatever the archive claims. */
    private const FORBIDDEN_EXTENSIONS = ['phar', 'phtml', 'phps', 'so', 'dll', 'exe', 'sh', 'bat', 'cmd'];

    private const MAX_ENTRIES = 30000;
    private const MAX_TOTAL_BYTES = 536870912; // 512 MB uncompressed
    private const MAX_COMPRESSION_RATIO = 250; // guards against a zip bomb

    /**
     * Normalise an archive entry to a safe relative path.
     *
     * Returns null when the entry must be refused: absolute paths, traversal
     * segments, NUL bytes, Windows drive letters, symlink-looking names or a
     * forbidden extension.
     */
    public static function safeRelativePath(string $entry): ?string
    {
        $entry = str_replace('\\', '/', $entry);

        if ($entry === '' || str_contains($entry, "\0")) {
            return null;
        }
        // Absolute paths and drive letters.
        if (str_starts_with($entry, '/') || preg_match('#^[A-Za-z]:#', $entry) === 1) {
            return null;
        }

        $segments = [];
        foreach (explode('/', $entry) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                // Never resolve upwards - refuse the entry outright.
                return null;
            }
            $segments[] = $segment;
        }
        if ($segments === []) {
            return null;
        }

        $path = implode('/', $segments);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            return null;
        }

        return $path;
    }

    /**
     * Does a relative path match one of the protected patterns?
     *
     * Patterns may be exact names, directory prefixes or shell globs
     * (e.g. "google*.html").
     *
     * @param array<int,string> $patterns
     */
    public static function isProtected(string $relative, array $patterns): bool
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        foreach ($patterns as $pattern) {
            $pattern = trim(str_replace('\\', '/', (string) $pattern), '/');
            if ($pattern === '') {
                continue;
            }
            if ($relative === $pattern || str_starts_with($relative, $pattern . '/')) {
                return true;
            }
            if (str_contains($pattern, '*') && fnmatch($pattern, $relative)) {
                return true;
            }
            // A bare filename pattern protects that name anywhere in the tree.
            if (!str_contains($pattern, '/') && basename($relative) === $pattern) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract a ZIP into a directory, stripping a single wrapping folder.
     *
     * GitHub archives contain everything under "repo-branch/", so that first
     * component is removed to give a clean tree.
     *
     * @return array{ok:bool,message:string,files:int,bytes:int,root:string|null}
     */
    public static function extractZip(string $archivePath, string $destination, bool $stripFirstComponent = true): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return [
                'ok'      => false,
                'message' => 'The PHP zip extension is required to apply updates.',
                'files'   => 0,
                'bytes'   => 0,
                'root'    => null,
            ];
        }
        if (!is_file($archivePath)) {
            return ['ok' => false, 'message' => 'The downloaded archive is missing.', 'files' => 0, 'bytes' => 0, 'root' => null];
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($archivePath, \ZipArchive::CHECKCONS);
        if ($opened !== true) {
            // CHECKCONS is strict; retry without it before giving up.
            $opened = $zip->open($archivePath);
        }
        if ($opened !== true) {
            return ['ok' => false, 'message' => 'The archive is not a valid ZIP file.', 'files' => 0, 'bytes' => 0, 'root' => null];
        }

        if ($zip->numFiles > self::MAX_ENTRIES) {
            $zip->close();
            return [
                'ok'      => false,
                'message' => 'The archive contains too many entries (' . $zip->numFiles . ').',
                'files'   => 0,
                'bytes'   => 0,
                'root'    => null,
            ];
        }

        if (!is_dir($destination) && !@mkdir($destination, 0755, true) && !is_dir($destination)) {
            $zip->close();
            return ['ok' => false, 'message' => 'Cannot create the staging directory.', 'files' => 0, 'bytes' => 0, 'root' => null];
        }
        $destinationReal = realpath($destination);
        if ($destinationReal === false) {
            $zip->close();
            return ['ok' => false, 'message' => 'The staging directory is unavailable.', 'files' => 0, 'bytes' => 0, 'root' => null];
        }

        $written = 0;
        $totalBytes = 0;
        $skipped = 0;
        $detectedRoot = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }
            $entry = (string) $stat['name'];
            $isDirectory = str_ends_with($entry, '/');

            $safe = self::safeRelativePath($entry);
            if ($safe === null) {
                if (!$isDirectory) {
                    $skipped++;
                    Logger::security('Refused an unsafe archive entry', ['entry' => $entry], );
                }
                continue;
            }

            if ($stripFirstComponent) {
                $parts = explode('/', $safe);
                $detectedRoot ??= $parts[0];
                array_shift($parts);
                if ($parts === []) {
                    continue; // this entry *was* the wrapper directory
                }
                $safe = implode('/', $parts);
            }

            $target = $destinationReal . '/' . $safe;

            if ($isDirectory) {
                if (!is_dir($target)) {
                    @mkdir($target, 0755, true);
                }
                continue;
            }

            // Zip bomb guard.
            $uncompressed = (int) ($stat['size'] ?? 0);
            $compressed = max(1, (int) ($stat['comp_size'] ?? 1));
            if ($uncompressed > 0 && ($uncompressed / $compressed) > self::MAX_COMPRESSION_RATIO && $uncompressed > 1048576) {
                $zip->close();
                return [
                    'ok'      => false,
                    'message' => 'The archive looks like a compression bomb and was rejected.',
                    'files'   => $written,
                    'bytes'   => $totalBytes,
                    'root'    => $detectedRoot,
                ];
            }
            $totalBytes += $uncompressed;
            if ($totalBytes > self::MAX_TOTAL_BYTES) {
                $zip->close();
                return [
                    'ok'      => false,
                    'message' => 'The archive expands beyond the permitted size.',
                    'files'   => $written,
                    'bytes'   => $totalBytes,
                    'root'    => $detectedRoot,
                ];
            }

            $directory = dirname($target);
            if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                $skipped++;
                continue;
            }

            // Final belt-and-braces check that we are still inside the
            // staging directory after the filesystem has had its say.
            $resolvedDirectory = realpath($directory);
            if ($resolvedDirectory === false
                || ($resolvedDirectory !== $destinationReal
                    && !str_starts_with($resolvedDirectory, $destinationReal . DIRECTORY_SEPARATOR))) {
                Logger::security('Archive entry resolved outside the staging directory', ['entry' => $entry]);
                $skipped++;
                continue;
            }

            $stream = $zip->getStream($entry);
            if ($stream === false) {
                $skipped++;
                continue;
            }
            $handle = fopen($target, 'wb');
            if ($handle === false) {
                fclose($stream);
                $skipped++;
                continue;
            }
            while (!feof($stream)) {
                $chunk = fread($stream, 262144);
                if ($chunk === false) {
                    break;
                }
                fwrite($handle, $chunk);
            }
            fclose($handle);
            fclose($stream);
            @chmod($target, 0644);
            $written++;
        }

        $zip->close();

        if ($written === 0) {
            return [
                'ok'      => false,
                'message' => 'The archive contained no usable files.',
                'files'   => 0,
                'bytes'   => $totalBytes,
                'root'    => $detectedRoot,
            ];
        }

        return [
            'ok'      => true,
            'message' => $written . ' file(s) extracted' . ($skipped > 0 ? ', ' . $skipped . ' refused' : '') . '.',
            'files'   => $written,
            'bytes'   => $totalBytes,
            'root'    => $detectedRoot,
        ];
    }

    /**
     * Recursively list files in a directory as relative paths.
     *
     * @return array<int,string>
     */
    public static function listFiles(string $directory): array
    {
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) {
            return [];
        }
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if (!$item->isFile()) {
                continue;
            }
            $out[] = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($root))), '/');
        }
        sort($out);
        return $out;
    }

    /** Recursively delete a directory. Refuses anything outside storage/. */
    public static function deleteDirectory(string $directory): bool
    {
        $real = realpath($directory);
        if ($real === false || !is_dir($real)) {
            return false;
        }
        $allowedRoots = array_filter([
            realpath(STORAGE_PATH),
            // Only when this host lets us see it at all.
            \App\Core\Path::isDir(sys_get_temp_dir()) ? realpath(sys_get_temp_dir()) : false,
        ]);
        $permitted = false;
        foreach ($allowedRoots as $root) {
            if (str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                $permitted = true;
                break;
            }
        }
        if (!$permitted) {
            Logger::security('Refused to delete a directory outside storage', ['path' => $directory]);
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        return @rmdir($real);
    }
}
