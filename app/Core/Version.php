<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Single source of truth for the application version.
 *
 * version.json is committed to the repository and is what the update system
 * compares against GitHub. Semantic versioning: MAJOR.MINOR.PATCH.
 */
final class Version
{
    private static ?array $data = null;

    public static function file(): string
    {
        return ROOT_PATH . '/version.json';
    }

    public static function data(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        $defaults = [
            'version'      => '1.0.0',
            'name'         => 'Shubh Kankotri',
            'released_at'  => null,
            'min_php'      => '8.0.0',
            'schema'       => 1,
        ];
        $file = self::file();
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $defaults = array_merge($defaults, $decoded);
            }
        }
        return self::$data = $defaults;
    }

    public static function current(): string
    {
        return (string) self::data()['version'];
    }

    public static function minPhp(): string
    {
        return (string) self::data()['min_php'];
    }

    /** Installed version as recorded in the database (may lag the files). */
    public static function installed(): string
    {
        try {
            $value = \App\Services\SettingsService::instance()->get('app_version');
            return is_string($value) && $value !== '' ? $value : self::current();
        } catch (\Throwable) {
            return self::current();
        }
    }

    public static function compare(string $a, string $b): int
    {
        return version_compare(self::normalise($a), self::normalise($b));
    }

    public static function isNewer(string $candidate, string $current): bool
    {
        return self::compare($candidate, $current) > 0;
    }

    public static function normalise(string $version): string
    {
        $version = ltrim(trim($version), 'vV');
        return preg_replace('/[^0-9.\-a-zA-Z]/', '', $version) ?? $version;
    }

    public static function refresh(): void
    {
        self::$data = null;
    }
}
// stale local edit
