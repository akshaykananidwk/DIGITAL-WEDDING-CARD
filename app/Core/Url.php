<?php

declare(strict_types=1);

namespace App\Core;

/** URL generation that works in a document root *or* a sub-directory. */
final class Url
{
    private static ?string $base = null;

    /** Base URL without trailing slash, e.g. https://example.com/cards */
    public static function base(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }
        $configured = (string) Config::get('app.url', '');
        self::$base = rtrim($configured !== '' ? $configured : self::detectBaseUrl(), '/');
        return self::$base;
    }

    public static function setBase(string $base): void
    {
        self::$base = rtrim($base, '/');
    }

    /** Best-effort detection from the current request. */
    public static function detectBaseUrl(): string
    {
        $scheme = self::isSecure() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $host = preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', (string) $host) ?? 'localhost';

        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($dir === '.' || $dir === '/') {
            $dir = '';
        }

        return $scheme . '://' . $host . $dir;
    }

    public static function isSecure(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        // Behind a load balancer / Cloudflare.
        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return true;
        }
        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
            return true;
        }
        return false;
    }

    /** Path portion of the base URL ('' for a document root install). */
    public static function basePath(): string
    {
        $path = parse_url(self::base(), PHP_URL_PATH);
        return is_string($path) ? rtrim($path, '/') : '';
    }

    public static function to(string $path = '/', array $query = []): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $url = self::base() . '/' . ltrim($path, '/');
        $url = rtrim($url, '/');
        if ($url === self::base()) {
            $url .= '/';
        }
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $url;
    }

    /**
     * A root-relative URL for something on this site.
     *
     * Used for assets, uploads and in-page navigation. Staying same-origin by
     * construction means a request arriving on a hostname that differs from
     * the configured site URL - www versus bare, an IP address, a staging
     * alias - still loads its own stylesheets, and the Content-Security-Policy
     * has nothing to second-guess. Absolute URLs are reserved for the places
     * that genuinely need one: emails, share links, QR codes, the sitemap and
     * canonical tags.
     */
    public static function path(string $path = '/', array $query = []): string
    {
        if (preg_match('#^(https?:)?//#i', $path) === 1 || str_starts_with($path, 'mailto:')) {
            return $path;
        }
        $url = self::basePath() . '/' . ltrim($path, '/');
        $url = rtrim($url, '/');
        if ($url === '') {
            $url = '/';
        }
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $url;
    }

    /** Asset URL, cache-busted by the file's modification time. */
    public static function asset(string $path): string
    {
        $relative = 'assets/' . ltrim($path, '/');
        $file = ROOT_PATH . '/' . $relative;
        $version = is_file($file) ? substr((string) filemtime($file), -6) : (string) Version::current();
        return self::path($relative) . '?v=' . $version;
    }

    public static function upload(string $path): string
    {
        return self::path('uploads/' . ltrim($path, '/'));
    }

    /** Public invitation URL for a slug. */
    public static function invite(string $slug): string
    {
        return self::to('invite/' . $slug);
    }

    /** Short public invitation URL (/i/CODE). */
    public static function shortInvite(string $code): string
    {
        return self::to('i/' . $code);
    }

    public static function current(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $scheme = self::isSecure() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $uri;
    }

    /**
     * Only allow redirects to our own host - prevents open-redirect abuse of
     * ?next= style parameters.
     */
    public static function safeRedirect(?string $candidate, string $fallback = '/dashboard'): string
    {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            return self::to($fallback);
        }
        if (str_starts_with($candidate, '//') || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $candidate)) {
            // Absolute URL: only accept when it points at our own base.
            if (str_starts_with($candidate, self::base() . '/') || $candidate === self::base()) {
                return $candidate;
            }
            return self::to($fallback);
        }
        return self::to($candidate);
    }
}
