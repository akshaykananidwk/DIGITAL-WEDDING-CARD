<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Fixed-window rate limiter backed by the cache directory.
 *
 * Protects login, registration, password reset, RSVP submission, the public
 * API and the AI endpoints. Keys are hashed so no raw IP hits the disk.
 */
final class RateLimiter
{
    private static function key(string $bucket, string $identity): string
    {
        return 'ratelimit:' . $bucket . ':' . hash('sha256', $identity . '|' . (string) Config::get('app.key', 'inv'));
    }

    /**
     * @return array{allowed:bool,remaining:int,retry_after:int,hits:int}
     */
    public static function hit(string $bucket, string $identity, int $maxAttempts, int $windowSeconds): array
    {
        $key = self::key($bucket, $identity);
        $now = time();
        $state = Cache::get($key);

        if (!is_array($state) || ($state['reset'] ?? 0) <= $now) {
            $state = ['hits' => 0, 'reset' => $now + $windowSeconds];
        }

        $state['hits'] = (int) $state['hits'] + 1;
        Cache::put($key, $state, max(1, (int) $state['reset'] - $now));

        $allowed = $state['hits'] <= $maxAttempts;

        return [
            'allowed'     => $allowed,
            'remaining'   => max(0, $maxAttempts - $state['hits']),
            'retry_after' => max(0, (int) $state['reset'] - $now),
            'hits'        => (int) $state['hits'],
        ];
    }

    /** Check without consuming an attempt. */
    public static function tooManyAttempts(string $bucket, string $identity, int $maxAttempts): bool
    {
        $state = Cache::get(self::key($bucket, $identity));
        if (!is_array($state)) {
            return false;
        }
        if (($state['reset'] ?? 0) <= time()) {
            return false;
        }
        return (int) ($state['hits'] ?? 0) >= $maxAttempts;
    }

    public static function retryAfter(string $bucket, string $identity): int
    {
        $state = Cache::get(self::key($bucket, $identity));
        if (!is_array($state)) {
            return 0;
        }
        return max(0, (int) ($state['reset'] ?? 0) - time());
    }

    public static function clear(string $bucket, string $identity): void
    {
        Cache::forget(self::key($bucket, $identity));
    }

    /**
     * Enforce a limit, throwing a 429 when exceeded.
     *
     * @return array{allowed:bool,remaining:int,retry_after:int,hits:int}
     */
    public static function enforce(string $bucket, string $identity, int $maxAttempts, int $windowSeconds): array
    {
        $result = self::hit($bucket, $identity, $maxAttempts, $windowSeconds);
        if (!$result['allowed']) {
            Logger::security('Rate limit exceeded', [
                'bucket'      => $bucket,
                'hits'        => $result['hits'],
                'retry_after' => $result['retry_after'],
            ]);
            throw new HttpException(
                429,
                'Too many requests. Please try again in ' . max(1, (int) ceil($result['retry_after'] / 60)) . ' minute(s).'
            );
        }
        return $result;
    }
}
