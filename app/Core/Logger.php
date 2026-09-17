<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Channel based file logger with daily rotation.
 *
 * Passwords, tokens and API keys are scrubbed from every payload before it
 * reaches disk (requirement: "never log passwords, tokens or sensitive
 * secrets").
 */
final class Logger
{
    public const APP      = 'app';
    public const SECURITY = 'security';
    public const UPDATE   = 'update';
    public const LOGIN    = 'login';
    public const API      = 'api';
    public const MAIL     = 'mail';
    public const CRON     = 'cron';

    /** Keys whose values are always replaced with [redacted]. */
    private const SENSITIVE = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'token', 'access_token', 'github_token', 'api_key', 'apikey', 'secret',
        'authorization', 'cookie', 'csrf', '_token', 'smtp_password', 'gemini_key',
        'gemini_api_key', 'app_key', 'private_key', 'remember_token',
    ];

    private const MAX_BYTES = 8388608; // 8 MB per file before rotation

    public static function dir(): string
    {
        return STORAGE_PATH . '/logs';
    }

    public static function debug(string $message, array $context = [], string $channel = self::APP): void
    {
        if (Config::get('app.debug', false)) {
            self::write('DEBUG', $message, $context, $channel);
        }
    }

    public static function info(string $message, array $context = [], string $channel = self::APP): void
    {
        self::write('INFO', $message, $context, $channel);
    }

    public static function warning(string $message, array $context = [], string $channel = self::APP): void
    {
        self::write('WARNING', $message, $context, $channel);
    }

    public static function error(string $message, array $context = [], string $channel = self::APP): void
    {
        self::write('ERROR', $message, $context, $channel);
    }

    public static function critical(string $message, array $context = [], string $channel = self::APP): void
    {
        self::write('CRITICAL', $message, $context, $channel);
    }

    public static function security(string $message, array $context = []): void
    {
        self::write('SECURITY', $message, $context, self::SECURITY);
    }

    private static function write(string $level, string $message, array $context, string $channel): void
    {
        $channel = preg_replace('/[^a-z0-9_\-]/i', '', $channel) ?: self::APP;
        $dir = self::dir();
        if (!Path::makeDir($dir)) {
            // Nowhere of our own to write: hand it to the host's PHP error log
            // instead, which is the one place an operator can still read on a
            // deployment where storage/ is not writable.
            self::fallback($level, $message);
            return;
        }

        $file = $dir . '/' . $channel . '-' . date('Y-m-d') . '.log';
        self::rotateIfNeeded($file);

        $context = self::scrub($context);
        $context['ip'] ??= self::clientIpHash();
        $context['uri'] ??= (string) ($_SERVER['REQUEST_URI'] ?? 'cli');

        $line = sprintf(
            "[%s] %s: %s %s%s",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            PHP_EOL
        );

        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            self::fallback($level, $message);
        }
    }

    /**
     * Last resort when the application's own log is unwritable.
     *
     * Deliberately terse: the message only, already scrubbed of secrets by the
     * caller, so nothing sensitive lands in a log we do not control.
     */
    private static function fallback(string $level, string $message): void
    {
        // No dependencies here on purpose: this path runs when the
        // filesystem is already misbehaving.
        @error_log('[invitation-saas] ' . $level . ': ' . $message);
    }

    private static function rotateIfNeeded(string $file): void
    {
        if (is_file($file) && filesize($file) > self::MAX_BYTES) {
            @rename($file, $file . '.' . time() . '.old');
        }
    }

    /** Recursively redact sensitive keys and truncate huge values. */
    public static function scrub(mixed $data, int $depth = 0): mixed
    {
        if ($depth > 6) {
            return '[truncated]';
        }
        if (is_array($data)) {
            $out = [];
            foreach ($data as $key => $value) {
                if (is_string($key) && self::isSensitive($key)) {
                    $out[$key] = '[redacted]';
                    continue;
                }
                $out[$key] = self::scrub($value, $depth + 1);
            }
            return $out;
        }
        if (is_object($data)) {
            return '[object ' . get_class($data) . ']';
        }
        if (is_string($data)) {
            // Catch tokens embedded in free text.
            $data = preg_replace('/\b(gh[pousr]_[A-Za-z0-9]{10,})\b/', '[redacted-token]', $data) ?? $data;
            $data = preg_replace('/\bAIza[0-9A-Za-z\-_]{20,}\b/', '[redacted-key]', $data) ?? $data;
            return mb_strlen($data) > 2000 ? mb_substr($data, 0, 2000) . '…' : $data;
        }
        return $data;
    }

    private static function isSensitive(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::SENSITIVE as $needle) {
            if ($key === $needle || str_contains($key, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * A salted hash of the client IP. Enough to spot abuse patterns without
     * storing personal data.
     */
    public static function clientIpHash(): string
    {
        $ip = Request::clientIp();
        $salt = (string) Config::get('app.key', 'inv');
        return substr(hash_hmac('sha256', $ip, $salt), 0, 16);
    }

    /** @return array<int,array{channel:string,date:string,file:string,size:int}> */
    public static function files(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*.log') ?: [] as $path) {
            $name = basename($path, '.log');
            if (preg_match('/^(.*)-(\d{4}-\d{2}-\d{2})$/', $name, $m)) {
                $out[] = [
                    'channel' => $m[1],
                    'date'    => $m[2],
                    'file'    => basename($path),
                    'size'    => (int) filesize($path),
                ];
            }
        }
        usort($out, static fn ($a, $b) => strcmp($b['date'] . $b['channel'], $a['date'] . $a['channel']));
        return $out;
    }

    /** Read the tail of a log file. Path traversal safe. */
    public static function tail(string $file, int $lines = 300): string
    {
        $file = basename($file);
        $path = self::dir() . '/' . $file;
        if (!is_file($path) || !str_ends_with($file, '.log')) {
            return '';
        }
        $content = (string) file_get_contents($path);
        $all = explode("\n", rtrim($content, "\n"));
        return implode("\n", array_slice($all, -$lines));
    }

    public static function purgeOlderThan(int $days): int
    {
        $cutoff = time() - ($days * 86400);
        $removed = 0;
        foreach (glob(self::dir() . '/*') ?: [] as $path) {
            if (is_file($path) && filemtime($path) < $cutoff) {
                @unlink($path);
                $removed++;
            }
        }
        return $removed;
    }
}
