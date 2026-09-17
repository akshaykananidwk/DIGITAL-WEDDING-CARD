<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File cache with atomic writes and tag-less namespacing.
 *
 * Used for template metadata, category trees, settings and analytics
 * aggregates - the reads that would otherwise repeat on every request.
 */
final class Cache
{
    private static array $memory = [];

    public static function dir(): string
    {
        return STORAGE_PATH . '/cache';
    }

    private static function path(string $key): string
    {
        $hash = hash('sha256', $key);
        $dir = self::dir() . '/' . substr($hash, 0, 2);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/' . $hash . '.cache';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$memory)) {
            return self::$memory[$key];
        }
        $path = self::path($key);
        if (!is_file($path)) {
            return $default;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return $default;
        }
        $payload = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($payload) || !array_key_exists('expires', $payload)) {
            @unlink($path);
            return $default;
        }
        if ($payload['expires'] !== 0 && $payload['expires'] < time()) {
            @unlink($path);
            return $default;
        }
        self::$memory[$key] = $payload['value'];
        return $payload['value'];
    }

    public static function put(string $key, mixed $value, ?int $ttl = null): void
    {
        $ttl ??= (int) Config::get('cache.ttl', 600);
        $payload = serialize([
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value'   => $value,
        ]);
        $path = self::path($key);
        $tmp = $path . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
            @rename($tmp, $path);
        }
        self::$memory[$key] = $value;
    }

    /** Get or compute-and-store. */
    public static function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        $sentinel = '__cache_miss__';
        $value = self::get($key, $sentinel);
        if ($value !== $sentinel) {
            return $value;
        }
        $value = $callback();
        self::put($key, $value, $ttl);
        return $value;
    }

    public static function forget(string $key): void
    {
        unset(self::$memory[$key]);
        $path = self::path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function has(string $key): bool
    {
        return self::get($key, '__miss__') !== '__miss__';
    }

    /** Remove everything. Returns the number of files deleted. */
    public static function flush(): int
    {
        self::$memory = [];
        $count = 0;
        foreach (glob(self::dir() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            foreach (glob($dir . '/*.cache') ?: [] as $file) {
                if (@unlink($file)) {
                    $count++;
                }
            }
            @rmdir($dir);
        }
        // Legacy flat files.
        foreach (glob(self::dir() . '/*.cache') ?: [] as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    /** Delete only expired entries (cron friendly). */
    public static function prune(): int
    {
        $count = 0;
        foreach (glob(self::dir() . '/*/*.cache') ?: [] as $file) {
            $raw = @file_get_contents($file);
            $payload = $raw === false ? null : @unserialize($raw, ['allowed_classes' => false]);
            if (!is_array($payload) || ($payload['expires'] !== 0 && $payload['expires'] < time())) {
                @unlink($file);
                $count++;
            }
        }
        return $count;
    }

    public static function stats(): array
    {
        $files = glob(self::dir() . '/*/*.cache') ?: [];
        $bytes = 0;
        foreach ($files as $file) {
            $bytes += (int) filesize($file);
        }
        return ['entries' => count($files), 'bytes' => $bytes];
    }
}
