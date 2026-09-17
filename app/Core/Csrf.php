<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Synchroniser-token CSRF protection.
 *
 * One token per session (rotated on login/logout), compared in constant time.
 * Accepted from the `_token` form field or the X-CSRF-Token header so AJAX
 * calls work without duplicating the field everywhere.
 */
final class Csrf
{
    public const FIELD  = '_token';
    public const HEADER = 'x-csrf-token';
    private const KEY   = '_csrf_token';

    public static function token(): string
    {
        Session::start();
        $token = Session::get(self::KEY);
        if (!is_string($token) || strlen($token) < 40) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    public static function rotate(): void
    {
        Session::set(self::KEY, bin2hex(random_bytes(32)));
    }

    public static function check(?string $candidate = null): bool
    {
        $request = Request::instance();
        $candidate ??= (string) ($request->raw(self::FIELD) ?? '');
        if ($candidate === '') {
            $candidate = $request->header(self::HEADER);
        }
        if ($candidate === '') {
            return false;
        }
        $expected = Session::get(self::KEY);
        return is_string($expected) && $expected !== '' && hash_equals($expected, $candidate);
    }

    /** Throws when the token is missing or wrong. */
    public static function verify(): void
    {
        if (!self::check()) {
            Logger::security('CSRF token mismatch', [
                'path'   => Request::instance()->path(),
                'method' => Request::instance()->method(),
            ]);
            throw new HttpException(419, 'Your session expired. Please refresh the page and try again.');
        }
    }
}
