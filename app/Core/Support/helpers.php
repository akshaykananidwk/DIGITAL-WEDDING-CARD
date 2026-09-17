<?php
/**
 * Global helper functions.
 *
 * Intentionally small: anything with real logic lives in a class. These exist
 * because views read far better with `e()` than with a static call.
 */

declare(strict_types=1);

use App\Core\Application;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\Lang;

if (!function_exists('e')) {
    /** HTML-escape a value for output in a template. */
    function e(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('eattr')) {
    /** Escape for use inside a single/double quoted HTML attribute. */
    function eattr(mixed $value): string
    {
        return e($value);
    }
}

if (!function_exists('ejs')) {
    /** Escape a value for embedding inside a <script> block. */
    function ejs(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?: 'null';
    }
}

if (!function_exists('config')) {
    /** Read a dotted configuration key. */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('app')) {
    function app(): Application
    {
        return Application::getInstance();
    }
}

if (!function_exists('url')) {
    /** Absolute URL for an application path. */
    function url(string $path = '/', array $query = []): string
    {
        return Url::to($path, $query);
    }
}

if (!function_exists('asset')) {
    /** Cache-busted URL for a bundled asset. */
    function asset(string $path): string
    {
        return Url::asset($path);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="' . Csrf::FIELD . '" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('old')) {
    /** Previously submitted value, for re-populating a failed form. */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = App\Core\Session::get('_old_input', []);
        return App\Core\Arr::get($old, $key, $default);
    }
}

if (!function_exists('errors')) {
    function errors(): array
    {
        return App\Core\Session::get('_errors', []);
    }
}

if (!function_exists('error_for')) {
    function error_for(string $key): ?string
    {
        $all = errors();
        return isset($all[$key]) ? (is_array($all[$key]) ? (string) reset($all[$key]) : (string) $all[$key]) : null;
    }
}

if (!function_exists('__')) {
    /** Translate a key for the active locale. */
    function __(string $key, array $replace = []): string
    {
        return Lang::get($key, $replace);
    }
}

if (!function_exists('setting')) {
    /** Read a persisted application setting. */
    function setting(string $key, mixed $default = null): mixed
    {
        return App\Services\SettingsService::instance()->get($key, $default);
    }
}

if (!function_exists('feature')) {
    /** Is a feature flag enabled? */
    function feature(string $key, bool $default = false): bool
    {
        return App\Services\FeatureFlagService::instance()->enabled($key, $default);
    }
}

if (!function_exists('str_random')) {
    function str_random(int $length = 32): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        return App\Core\Arr::get($array, $key, $default);
    }
}

if (!function_exists('dd')) {
    /** Debug helper - only ever prints when debug mode is on. */
    function dd(mixed ...$values): void
    {
        if (!Config::get('app.debug', false)) {
            return;
        }
        echo '<pre style="background:#111;color:#0f0;padding:1rem;overflow:auto">';
        foreach ($values as $value) {
            echo e(print_r($value, true)) . "\n";
        }
        echo '</pre>';
        exit;
    }
}
