<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Translation store.
 *
 * Locale files live in app/Lang/<locale>.php and return a nested array.
 * Missing keys fall back to English and finally to the key itself, so adding
 * a language never breaks a page.
 */
final class Lang
{
    private const FALLBACK = 'en';
    private const SESSION_KEY = '_locale';

    private static string $locale = 'en';
    /** @var array<string,array<string,mixed>> */
    private static array $loaded = [];
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        $available = array_keys(self::available());

        // Priority: explicit ?lang= → session → user preference → setting → config
        $candidate = null;
        $query = Request::instance()->query('lang');
        if (is_string($query) && $query !== '' && in_array($query, $available, true)) {
            $candidate = $query;
            Session::set(self::SESSION_KEY, $candidate);
        }
        if ($candidate === null) {
            $stored = Session::get(self::SESSION_KEY);
            if (is_string($stored) && in_array($stored, $available, true)) {
                $candidate = $stored;
            }
        }
        if ($candidate === null) {
            $user = Auth::user();
            $pref = is_array($user) ? ($user['locale'] ?? null) : null;
            if (is_string($pref) && in_array($pref, $available, true)) {
                $candidate = $pref;
            }
        }
        if ($candidate === null) {
            $default = (string) Config::get('app.locale', 'en');
            $candidate = in_array($default, $available, true) ? $default : self::FALLBACK;
        }

        self::$locale = $candidate;
    }

    /** @return array<string,string> locale code => display name */
    public static function available(): array
    {
        $configured = (array) Config::get('app.locales', ['en' => 'English']);
        $out = [];
        foreach ($configured as $code => $name) {
            if (is_file(APP_PATH . '/Lang/' . $code . '.php')) {
                $out[(string) $code] = (string) $name;
            }
        }
        return $out !== [] ? $out : ['en' => 'English'];
    }

    public static function locale(): string
    {
        self::boot();
        return self::$locale;
    }

    public static function setLocale(string $locale): void
    {
        if (array_key_exists($locale, self::available())) {
            self::$locale = $locale;
            self::$booted = true;
            Session::set(self::SESSION_KEY, $locale);
        }
    }

    /** HTML lang attribute value. */
    public static function htmlLang(): string
    {
        return match (self::locale()) {
            'gu' => 'gu-IN',
            'hi' => 'hi-IN',
            default => 'en',
        };
    }

    public static function get(string $key, array $replace = []): string
    {
        self::boot();
        $value = self::lookup(self::$locale, $key);
        if ($value === null && self::$locale !== self::FALLBACK) {
            $value = self::lookup(self::FALLBACK, $key);
        }
        if ($value === null) {
            // Final fallback: the last segment, humanised. Never show "a.b.c".
            $segments = explode('.', $key);
            $value = ucfirst(str_replace('_', ' ', end($segments)));
        }
        foreach ($replace as $search => $replacement) {
            $value = str_replace([':' . $search, '{' . $search . '}'], (string) $replacement, $value);
        }
        return $value;
    }

    public static function has(string $key): bool
    {
        self::boot();
        return self::lookup(self::$locale, $key) !== null || self::lookup(self::FALLBACK, $key) !== null;
    }

    private static function lookup(string $locale, string $key): ?string
    {
        if (!isset(self::$loaded[$locale])) {
            $file = APP_PATH . '/Lang/' . preg_replace('/[^a-z_\-]/i', '', $locale) . '.php';
            $data = is_file($file) ? require $file : [];
            self::$loaded[$locale] = is_array($data) ? $data : [];
        }
        $value = Arr::get(self::$loaded[$locale], $key);
        return is_string($value) ? $value : null;
    }

    /** Whole translation table for the active locale (used by the JS bundle). */
    public static function all(): array
    {
        self::boot();
        self::lookup(self::$locale, 'x');
        return self::$loaded[self::$locale] ?? [];
    }
}
