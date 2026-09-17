<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Central configuration store.
 *
 * Values come from three places, in order of precedence:
 *   1. Runtime overrides set with Config::set()
 *   2. The installer-generated secret file (database credentials, app key).
 *      Stored OUTSIDE the public web root when the parent directory is
 *      writable, otherwise in storage/ which is denied by .htaccess.
 *   3. app/config/defaults.php - safe, non secret defaults committed to git.
 *
 * Secrets are therefore never part of the repository and survive updates.
 */
final class Config
{
    /** Directory name used when secrets can live outside the web root. */
    public const EXTERNAL_DIR = '.invitation-secrets';

    /** @var array<string,mixed> */
    private static array $items = [];

    private static bool $loaded = false;

    private static ?string $secretFile = null;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $defaults = require APP_PATH . '/config/defaults.php';
        self::$items = is_array($defaults) ? $defaults : [];

        $secrets = self::readSecretFile();
        if ($secrets !== null) {
            self::$items = Arr::mergeDeep(self::$items, $secrets);
        }

        // A .env file is supported for hosts/teams that prefer it. It only ever
        // *adds* to the configuration; the PHP secret file wins.
        $env = self::readEnvFile(ROOT_PATH . '/.env');
        foreach ($env as $key => $value) {
            $mapped = self::mapEnvKey($key);
            if ($mapped !== null && self::get($mapped) === null) {
                self::set($mapped, $value);
            }
        }

        if (self::get('app.url') === null || self::get('app.url') === '') {
            self::set('app.url', Url::detectBaseUrl());
        }
    }

    /** Candidate locations for the secret file, most preferred first. */
    public static function secretCandidates(): array
    {
        return [
            dirname(ROOT_PATH) . '/' . self::EXTERNAL_DIR . '/app.php',
            STORAGE_PATH . '/config/app.php',
        ];
    }

    /** Path of the secret file currently in use (null when not installed). */
    public static function secretFile(): ?string
    {
        if (self::$secretFile === null) {
            foreach (self::secretCandidates() as $candidate) {
                if (is_file($candidate)) {
                    self::$secretFile = $candidate;
                    break;
                }
            }
        }
        return self::$secretFile;
    }

    /** Where a fresh install should write its secret file. */
    public static function preferredSecretTarget(): string
    {
        $candidates = self::secretCandidates();
        $external = $candidates[0];
        $externalDir = dirname($external);
        if (is_dir($externalDir) && is_writable($externalDir)) {
            return $external;
        }
        if (!is_dir($externalDir) && is_writable(dirname($externalDir))) {
            return $external;
        }
        return $candidates[1];
    }

    /** @return array<string,mixed>|null */
    private static function readSecretFile(): ?array
    {
        $file = self::secretFile();
        if ($file === null) {
            return null;
        }
        /** @psalm-suppress UnresolvableInclude */
        $data = require $file;
        return is_array($data) ? $data : null;
    }

    /**
     * Minimal .env reader - no external dependency, no shell evaluation.
     *
     * @return array<string,string>
     */
    public static function readEnvFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (strlen($value) > 1 && (
                ($value[0] === '"' && str_ends_with($value, '"')) ||
                ($value[0] === "'" && str_ends_with($value, "'"))
            )) {
                $value = substr($value, 1, -1);
            }
            $out[$key] = $value;
        }
        return $out;
    }

    private static function mapEnvKey(string $key): ?string
    {
        static $map = [
            'APP_NAME'    => 'app.name',
            'APP_URL'     => 'app.url',
            'APP_KEY'     => 'app.key',
            'APP_DEBUG'   => 'app.debug',
            'APP_LOCALE'  => 'app.locale',
            'DB_HOST'     => 'database.host',
            'DB_PORT'     => 'database.port',
            'DB_DATABASE' => 'database.database',
            'DB_USERNAME' => 'database.username',
            'DB_PASSWORD' => 'database.password',
            'DB_DRIVER'   => 'database.driver',
            'DB_PREFIX'   => 'database.prefix',
        ];
        return $map[$key] ?? null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return Arr::get(self::$items, $key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        self::load();
        Arr::set(self::$items, $key, $value);
    }

    public static function all(): array
    {
        self::load();
        return self::$items;
    }

    /** True once the installer has written a usable configuration. */
    public static function isInstalled(): bool
    {
        if (self::secretFile() === null) {
            return false;
        }
        return (string) self::get('database.database', '') !== ''
            && (string) self::get('app.key', '') !== '';
    }

    /**
     * Persist the secret configuration file.
     *
     * @param array<string,mixed> $data
     */
    public static function writeSecretFile(array $data, ?string $target = null): string
    {
        $target ??= self::preferredSecretTarget();
        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create configuration directory: ' . $dir);
        }

        $export = "<?php\n"
            . "/**\n"
            . " * Generated by the Invitation Card SaaS installer.\n"
            . " * Contains secrets - never commit this file, never expose it publicly.\n"
            . " * Generated: " . gmdate('c') . "\n"
            . " */\n\n"
            . "return " . self::exportArray($data, 0) . ";\n";

        if (file_put_contents($target, $export, LOCK_EX) === false) {
            throw new \RuntimeException('Cannot write configuration file: ' . $target);
        }
        @chmod($target, 0640);

        // Defence in depth for the in-webroot fallback location.
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents(
                $htaccess,
                "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n"
            );
        }

        self::$secretFile = $target;
        self::$loaded = false;
        self::$items = [];
        self::load();

        return $target;
    }

    private static function exportArray(array $data, int $indent): string
    {
        $pad = str_repeat('    ', $indent + 1);
        $out = "[\n";
        foreach ($data as $key => $value) {
            $out .= $pad . var_export($key, true) . ' => ';
            if (is_array($value)) {
                $out .= self::exportArray($value, $indent + 1);
            } else {
                $out .= var_export($value, true);
            }
            $out .= ",\n";
        }
        return $out . str_repeat('    ', $indent) . ']';
    }
}
