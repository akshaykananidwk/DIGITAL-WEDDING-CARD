<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hardened session wrapper.
 *
 *  - HttpOnly + SameSite cookies, Secure automatically over HTTPS
 *  - strict mode (no session fixation via attacker supplied ids)
 *  - id rotation on login and every N seconds
 *  - idle timeout
 *  - optional database driver (the `sessions` table) for multi-server hosts
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || PHP_SAPI === 'cli') {
            self::$started = true;
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $name = (string) Config::get('session.name', 'inv_session');
        $lifetime = (int) Config::get('session.lifetime', 7200);

        if ((string) Config::get('session.driver', 'file') === 'database' && Config::isInstalled()) {
            try {
                session_set_save_handler(new DatabaseSessionHandler(), true);
            } catch (\Throwable $e) {
                Logger::warning('Falling back to file sessions: ' . $e->getMessage());
                self::configureFileStore();
            }
        } else {
            self::configureFileStore();
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) max(1440, $lifetime));
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '5');

        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0, // browser session cookie; idle timeout is enforced server side
            'path'     => Url::basePath() . '/' ?: '/',
            'domain'   => '',
            'secure'   => Url::isSecure(),
            'httponly' => true,
            'samesite' => (string) Config::get('session.same_site', 'Lax'),
        ]);

        session_start();
        self::$started = true;

        self::enforceIdleTimeout($lifetime);
        self::rotatePeriodically();
        self::bindToClient();
    }

    private static function configureFileStore(): void
    {
        $path = (string) (Config::get('session.path') ?? STORAGE_PATH . '/sessions');
        if (!is_dir($path)) {
            @mkdir($path, 0700, true);
        }
        if (is_dir($path) && is_writable($path)) {
            session_save_path($path);
        }
    }

    private static function enforceIdleTimeout(int $lifetime): void
    {
        $last = (int) ($_SESSION['_last_activity'] ?? 0);
        if ($last > 0 && (time() - $last) > $lifetime) {
            self::flushAndRegenerate();
            $_SESSION['_expired'] = true;
        }
        $_SESSION['_last_activity'] = time();
    }

    private static function rotatePeriodically(): void
    {
        $interval = (int) Config::get('session.rotate', 1800);
        if ($interval <= 0) {
            return;
        }
        $created = (int) ($_SESSION['_created_at'] ?? 0);
        if ($created === 0) {
            $_SESSION['_created_at'] = time();
            return;
        }
        if (time() - $created > $interval) {
            session_regenerate_id(true);
            $_SESSION['_created_at'] = time();
        }
    }

    /**
     * Bind the session to a coarse client signature. A stolen cookie replayed
     * from a different user agent is rejected. (IP is intentionally not part of
     * the signature - mobile networks change it constantly.)
     */
    private static function bindToClient(): void
    {
        $signature = hash_hmac(
            'sha256',
            Request::instance()->userAgent(),
            (string) Config::get('app.key', 'inv')
        );
        if (!isset($_SESSION['_client'])) {
            $_SESSION['_client'] = $signature;
            return;
        }
        if (!hash_equals((string) $_SESSION['_client'], $signature)) {
            Logger::security('Session client signature mismatch - session discarded');
            self::flushAndRegenerate();
            $_SESSION['_client'] = $signature;
        }
    }

    public static function flushAndRegenerate(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['_created_at'] = time();
        $_SESSION['_last_activity'] = time();
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_created_at'] = time();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    public static function all(): array
    {
        self::start();
        return $_SESSION;
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
            session_destroy();
        }
        self::$started = false;
    }

    // ---------------- flash messages & form state ----------------

    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function takeFlash(): array
    {
        self::start();
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($flash) ? $flash : [];
    }

    public static function flashInput(array $input): void
    {
        self::set('_old_input', Arr::except($input, ['password', 'password_confirmation', '_token', '_method']));
    }

    public static function flashErrors(array $errors): void
    {
        self::set('_errors', $errors);
    }

    /** Clear one-request state that has already been rendered. */
    public static function clearFormState(): void
    {
        self::forget('_old_input');
        self::forget('_errors');
    }
}
