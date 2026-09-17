<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;
use App\Services\AuditService;

/**
 * Session based authentication with role/permission checks.
 *
 * - password_hash()/password_verify() with the platform default algorithm and
 *   automatic re-hashing when the cost or algorithm changes
 * - throttled login attempts per email *and* per IP
 * - session id rotated on every successful login
 * - "remember me" uses a selector+validator pair so a stolen database row
 *   cannot be replayed as a cookie
 */
final class Auth
{
    private const SESSION_USER = 'user_id';
    private const REMEMBER_COOKIE = 'inv_remember';

    private static ?array $user = null;
    private static bool $resolved = false;
    private static bool $tokenAuthenticated = false;
    /** @var array<int,string>|null */
    private static ?array $permissions = null;

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    /**
     * Check credentials, and sign the user in unless a second factor is due.
     *
     * @param bool $credentialsOnly return the user without creating a session,
     *                              so the caller can require an OTP first
     * @return array{ok:bool,message:string,user:array<string,mixed>|null}
     */
    public static function attempt(
        string $email,
        string $password,
        bool $remember = false,
        bool $credentialsOnly = false
    ): array
    {
        $email = strtolower(trim($email));
        $ip = Request::clientIp();
        $maxAttempts = (int) Config::get('security.login_max_attempts', 5);
        $decay = (int) Config::get('security.login_decay_minutes', 15) * 60;

        // Two buckets: a targeted attack on one account, and a spray from one IP.
        if (RateLimiter::tooManyAttempts('login-email', $email, $maxAttempts)
            || RateLimiter::tooManyAttempts('login-ip', $ip, $maxAttempts * 4)) {
            $retry = max(
                RateLimiter::retryAfter('login-email', $email),
                RateLimiter::retryAfter('login-ip', $ip)
            );
            Logger::security('Login blocked by throttle', [
                'email_hash' => substr(hash('sha256', $email), 0, 12),
            ]);
            return [
                'ok'      => false,
                'message' => 'Too many failed attempts. Please try again in '
                    . max(1, (int) ceil($retry / 60)) . ' minute(s).',
                'user'    => null,
            ];
        }

        $users = new UserRepository();
        $user = $users->findByEmail($email);

        // Always run a hash comparison so the response time does not reveal
        // whether the account exists.
        $storedHash = is_array($user) ? (string) $user['password'] : '$2y$10$'
            . str_repeat('x', 53);
        $valid = password_verify($password, $storedHash);

        if (!is_array($user) || !$valid) {
            RateLimiter::hit('login-email', $email, $maxAttempts, $decay);
            RateLimiter::hit('login-ip', $ip, $maxAttempts * 4, $decay);
            Logger::info('Login failed', [
                'email_hash' => substr(hash('sha256', $email), 0, 12),
                'exists'     => is_array($user),
            ], Logger::LOGIN);
            return ['ok' => false, 'message' => 'The email address or password is incorrect.', 'user' => null];
        }

        if ((string) $user['status'] !== 'active') {
            Logger::security('Login rejected: inactive account', ['user_id' => $user['id']]);
            return [
                'ok'      => false,
                'message' => $user['status'] === 'suspended'
                    ? 'This account has been suspended. Please contact support.'
                    : 'This account is not active yet. Please verify your email address.',
                'user'    => null,
            ];
        }

        if (self::needsRehash($storedHash)) {
            $users->update((int) $user['id'], ['password' => self::hash($password)]);
        }

        RateLimiter::clear('login-email', $email);
        RateLimiter::clear('login-ip', $ip);

        // A second factor, where the user has one, goes between a correct
        // password and a signed-in session: the caller completes the login.
        if ($credentialsOnly) {
            return ['ok' => true, 'message' => 'Credentials accepted.', 'user' => $user];
        }

        self::login($user, $remember);

        return ['ok' => true, 'message' => 'Welcome back!', 'user' => $user];
    }

    public static function login(array $user, bool $remember = false): void
    {
        Session::start();
        Session::regenerate();
        Csrf::rotate();

        Session::set(self::SESSION_USER, (int) $user['id']);
        Session::set('_login_at', time());
        self::$user = $user;
        self::$resolved = true;
        self::$permissions = null;

        $users = new UserRepository();
        $users->touchLogin((int) $user['id']);

        if ($remember) {
            self::issueRememberCookie((int) $user['id']);
        }

        Logger::info('Login successful', ['user_id' => $user['id']], Logger::LOGIN);
        AuditService::instance()->log('auth.login', 'user', (int) $user['id']);
    }

    /**
     * Authenticate for this request only, without touching the session.
     *
     * A bearer token carries its own credential on every request, so there is
     * nothing to remember between them: no session is started, no cookie is
     * set and no login is recorded. It also leaves the request distinguishable
     * from a cookie-authenticated one, which is what lets the CSRF check skip
     * a token call safely.
     */
    public static function actAsToken(array $user): void
    {
        self::$user = $user;
        self::$resolved = true;
        self::$permissions = null;
        self::$tokenAuthenticated = true;
    }

    /** Was this request authenticated by a bearer token rather than a cookie? */
    public static function isTokenAuthenticated(): bool
    {
        return self::$tokenAuthenticated;
    }

    public static function logout(): void
    {
        $userId = self::id();
        if ($userId !== null) {
            AuditService::instance()->log('auth.logout', 'user', $userId);
            Logger::info('Logout', ['user_id' => $userId], Logger::LOGIN);
            (new UserRepository())->clearRememberToken($userId);
        }
        self::clearRememberCookie();
        self::$user = null;
        self::$resolved = true;
        self::$permissions = null;
        Session::destroy();
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        if (!Config::isInstalled()) {
            return self::$user = null;
        }

        Session::start();
        $id = Session::get(self::SESSION_USER);

        if (!is_numeric($id)) {
            return self::$user = self::attemptRememberLogin();
        }

        try {
            $user = (new UserRepository())->find((int) $id);
        } catch (\Throwable $e) {
            Logger::warning('Could not resolve session user: ' . $e->getMessage());
            return self::$user = null;
        }

        if (!is_array($user) || (string) $user['status'] !== 'active') {
            Session::forget(self::SESSION_USER);
            return self::$user = null;
        }

        return self::$user = $user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return is_array($user) ? (int) $user['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    /** Refresh the cached user row after a profile update. */
    public static function refresh(): void
    {
        self::$resolved = false;
        self::$user = null;
        self::$permissions = null;
    }

    // ------------------------------------------------------------------
    //  Roles & permissions (RBAC)
    // ------------------------------------------------------------------

    public static function role(): string
    {
        $user = self::user();
        return is_array($user) ? (string) ($user['role_slug'] ?? 'user') : 'guest';
    }

    public static function hasRole(string ...$roles): bool
    {
        $role = self::role();
        foreach ($roles as $candidate) {
            if ($role === $candidate) {
                return true;
            }
        }
        return false;
    }

    public static function isSuperAdmin(): bool
    {
        return self::role() === 'super-admin';
    }

    /** Anyone who may enter /admin. */
    public static function isAdmin(): bool
    {
        return self::hasRole('super-admin', 'admin', 'editor');
    }

    /** @return array<int,string> */
    public static function permissions(): array
    {
        if (self::$permissions !== null) {
            return self::$permissions;
        }
        $user = self::user();
        if (!is_array($user)) {
            return self::$permissions = [];
        }
        try {
            self::$permissions = (new UserRepository())->permissionsForRole((int) $user['role_id']);
        } catch (\Throwable) {
            self::$permissions = [];
        }
        return self::$permissions;
    }

    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::isSuperAdmin()) {
            return true; // super admin implicitly holds every permission
        }
        $permissions = self::permissions();
        if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
            return true;
        }
        // Wildcard support: templates.* grants templates.create
        $group = explode('.', $permission)[0] . '.*';
        return in_array($group, $permissions, true);
    }

    public static function cannot(string $permission): bool
    {
        return !self::can($permission);
    }

    /** Throws 403 unless the permission is held. */
    public static function authorize(string $permission): void
    {
        if (!self::can($permission)) {
            Logger::security('Authorization denied', [
                'permission' => $permission,
                'user_id'    => self::id(),
                'role'       => self::role(),
            ]);
            throw HttpException::forbidden('You do not have permission to perform this action.');
        }
    }

    /** Ownership check used everywhere a user-owned record is touched (IDOR). */
    public static function ownsOrFail(?array $record, string $ownerColumn = 'user_id'): array
    {
        if (!is_array($record)) {
            throw HttpException::notFound();
        }
        $userId = self::id();
        if ($userId === null) {
            throw HttpException::unauthorized();
        }
        if ((int) ($record[$ownerColumn] ?? 0) === $userId) {
            return $record;
        }
        if (self::can('invitations.manage_all')) {
            return $record;
        }
        Logger::security('Ownership check failed', [
            'user_id' => $userId,
            'owner'   => $record[$ownerColumn] ?? null,
        ]);
        // 404 rather than 403: do not confirm the record exists.
        throw HttpException::notFound();
    }

    // ------------------------------------------------------------------
    //  Remember me
    // ------------------------------------------------------------------

    private static function issueRememberCookie(int $userId): void
    {
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + (60 * 60 * 24 * 30);

        (new UserRepository())->storeRememberToken($userId, $selector, hash('sha256', $validator), $expires);

        setcookie(self::REMEMBER_COOKIE, $selector . ':' . $validator, [
            'expires'  => $expires,
            'path'     => Url::basePath() . '/' ?: '/',
            'secure'   => Url::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearRememberCookie(): void
    {
        if (!isset($_COOKIE[self::REMEMBER_COOKIE])) {
            return;
        }
        unset($_COOKIE[self::REMEMBER_COOKIE]);
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => Url::basePath() . '/' ?: '/',
            'secure'   => Url::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** @return array<string,mixed>|null */
    private static function attemptRememberLogin(): ?array
    {
        $cookie = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if ($cookie === '' || !str_contains($cookie, ':')) {
            return null;
        }
        [$selector, $validator] = explode(':', $cookie, 2);
        if (strlen($selector) !== 18 || strlen($validator) !== 64) {
            self::clearRememberCookie();
            return null;
        }

        try {
            $users = new UserRepository();
            $user = $users->findByRememberSelector($selector);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($user)) {
            self::clearRememberCookie();
            return null;
        }
        if ((int) ($user['remember_expires'] ?? 0) < time()
            || !hash_equals((string) $user['remember_validator'], hash('sha256', $validator))) {
            Logger::security('Invalid remember-me token presented', ['user_id' => $user['id'] ?? null]);
            (new UserRepository())->clearRememberToken((int) $user['id']);
            self::clearRememberCookie();
            return null;
        }
        if ((string) $user['status'] !== 'active') {
            return null;
        }

        // Rotate the validator on every use so a replay is detectable.
        self::login($user, true);
        return $user;
    }
}
