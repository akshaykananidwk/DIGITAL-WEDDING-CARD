<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Filesystem probes that are allowed to fail.
 *
 * Shared hosting usually sets `open_basedir` to the account's own directory,
 * and many panels narrow it to the document root. Touching a path outside it -
 * which this application does on purpose, to keep secrets and backups out of
 * the web root - raises a PHP warning, and a warning is an exception here.
 * That turned a hardening measure into a blank 500 on some hosts.
 *
 * So: ask first. `allowed()` answers whether a path is even reachable, and the
 * probes below never raise, they just answer false when they cannot look.
 */
final class Path
{
    /** @var array<int,string>|null the open_basedir entries, resolved once */
    private static ?array $allowedRoots = null;

    /** Is this path reachable at all under the current open_basedir? */
    public static function allowed(string $path): bool
    {
        $roots = self::allowedRoots();
        if ($roots === []) {
            return true; // No restriction configured.
        }

        // open_basedir compares path prefixes, and it is applied to the path as
        // written, so an unresolvable path is checked by its nearest existing
        // ancestor rather than rejected outright.
        $candidate = self::normalise($path);
        foreach ($roots as $root) {
            if ($candidate === $root || str_starts_with($candidate, rtrim($root, '/') . '/')) {
                return true;
            }
        }
        return false;
    }

    public static function isFile(string $path): bool
    {
        return self::allowed($path) && @is_file($path);
    }

    public static function isDir(string $path): bool
    {
        return self::allowed($path) && @is_dir($path);
    }

    public static function isWritable(string $path): bool
    {
        return self::allowed($path) && @is_writable($path);
    }

    /** Create a directory, answering whether it exists and is usable afterwards. */
    public static function makeDir(string $path, int $mode = 0755): bool
    {
        if (!self::allowed($path)) {
            return false;
        }
        if (@is_dir($path)) {
            return true;
        }
        return @mkdir($path, $mode, true) || @is_dir($path);
    }

    /** The configured open_basedir entries, or an empty list when unrestricted. */
    public static function restrictions(): array
    {
        return self::allowedRoots();
    }

    /** For the installer and the health screen: is a restriction in force? */
    public static function isRestricted(): bool
    {
        return self::allowedRoots() !== [];
    }

    /**
     * A writable temporary directory this process can definitely reach.
     *
     * `sys_get_temp_dir()` is usually /tmp, which sits outside open_basedir on
     * a restricted host - that is what made PDF export fail there. storage/tmp
     * ships with the application, so it is inside the root by construction.
     */
    public static function tempDir(): string
    {
        $own = STORAGE_PATH . '/tmp';
        if (self::makeDir($own) && self::isWritable($own)) {
            return $own;
        }

        $system = sys_get_temp_dir();
        if (self::isDir($system) && self::isWritable($system)) {
            return $system;
        }
        return $own; // Report the intended location; the caller reports the failure.
    }

    /**
     * Create a temporary file inside a reachable directory.
     *
     * @return string|null the path, or null when no temporary file can be made
     */
    public static function tempFile(string $prefix = 'tmp'): ?string
    {
        $prefix = preg_replace('/[^A-Za-z0-9_\-]/', '', $prefix) ?: 'tmp';
        $path = @tempnam(self::tempDir(), $prefix);

        return is_string($path) && $path !== '' ? $path : null;
    }

    /** Reset the cached restriction list (tests). */
    public static function flush(): void
    {
        self::$allowedRoots = null;
    }

    /** @return array<int,string> */
    private static function allowedRoots(): array
    {
        if (self::$allowedRoots !== null) {
            return self::$allowedRoots;
        }

        $setting = trim((string) ini_get('open_basedir'));
        if ($setting === '' || $setting === 'none') {
            return self::$allowedRoots = [];
        }

        $roots = [];
        foreach (explode(PATH_SEPARATOR, $setting) as $entry) {
            $entry = trim($entry);
            if ($entry !== '') {
                $roots[] = self::normalise($entry);
            }
        }
        return self::$allowedRoots = $roots;
    }

    /** Collapse `.` and `..` without touching the disk. */
    private static function normalise(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $absolute = str_starts_with($path, '/');
        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }
        return ($absolute ? '/' : '') . implode('/', $parts);
    }
}
