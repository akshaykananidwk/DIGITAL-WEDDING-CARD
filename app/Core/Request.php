<?php

declare(strict_types=1);

namespace App\Core;

/** Immutable-ish wrapper around the current HTTP request. */
final class Request
{
    private static ?self $instance = null;

    /** @var array<string,mixed> */
    private array $query;
    /** @var array<string,mixed> */
    private array $body;
    /** @var array<string,mixed> */
    private array $files;
    /** @var array<string,string> */
    private array $headers;
    /** @var array<string,string> */
    private array $routeParams = [];

    private string $method;
    private string $path;

    private function __construct()
    {
        $this->query = $_GET;
        $this->headers = self::collectHeaders();
        $this->files = $_FILES;
        $this->method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $this->path = self::resolvePath();
        $this->body = $this->resolveBody();

        // Support method spoofing from HTML forms (_method=PUT/PATCH/DELETE).
        if ($this->method === 'POST') {
            $spoof = strtoupper((string) ($this->body['_method'] ?? ''));
            if (in_array($spoof, ['PUT', 'PATCH', 'DELETE'], true)) {
                $this->method = $spoof;
            }
        }
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** @return array<string,string> */
    private static function collectHeaders(): array
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() ?: [] as $key => $value) {
                $headers[strtolower((string) $key)] = (string) $value;
            }
        }
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr((string) $key, 5)));
                $headers[$name] ??= (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] ??= (string) $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }

    /** Request path relative to the application base, always starting with '/'. */
    private static function resolvePath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = rawurldecode($path);

        // Strip the sub-directory the app is installed in.
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(dirname($script), '/');
        if ($dir !== '' && $dir !== '.' && str_starts_with($path, $dir)) {
            $path = substr($path, strlen($dir));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }

        $path = '/' . trim($path, '/');
        // Collapse duplicate slashes and reject traversal segments outright.
        $path = preg_replace('#/+#', '/', $path) ?? '/';
        return $path === '' ? '/' : $path;
    }

    /** @return array<string,mixed> */
    private function resolveBody(): array
    {
        $type = strtolower($this->header('content-type', ''));
        if (str_contains($type, 'application/json')) {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            return [];
        }
        if ($this->method !== 'GET' && $this->method !== 'POST' && $_POST === []) {
            // PUT/PATCH/DELETE with urlencoded body.
            $raw = file_get_contents('php://input');
            if (is_string($raw) && $raw !== '') {
                $parsed = [];
                parse_str($raw, $parsed);
                return $parsed;
            }
        }
        return $_POST;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function header(string $name, ?string $default = null): string
    {
        return $this->headers[strtolower($name)] ?? (string) $default;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * A request value, from the route first, then the body, then the query.
     *
     * The route wins deliberately: /admin/users/7/status identifies user 7,
     * and a posted "id" field must not be able to redirect that write to
     * another row.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->routeParams)) {
            return $this->routeParams[$key];
        }
        $value = Arr::get($this->body, $key, Arr::get($this->query, $key, $default));
        return is_string($value) ? trim($value) : $value;
    }

    /** Raw (untrimmed) value - needed for password fields and textareas. */
    public function raw(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->body, $key, Arr::get($this->query, $key, $default));
    }

    public function query(string $key, mixed $default = null): mixed
    {
        $value = Arr::get($this->query, $key, $default);
        return is_string($value) ? trim($value) : $value;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key, null);
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }

    public function array(string $key, array $default = []): array
    {
        $value = Arr::get($this->body, $key, Arr::get($this->query, $key, $default));
        return is_array($value) ? $value : $default;
    }

    public function has(string $key): bool
    {
        return Arr::has($this->body, $key) || Arr::has($this->query, $key);
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '' && $value !== [];
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** @return array<string,mixed> */
    public function only(array $keys): array
    {
        $all = $this->all();
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $out[$key] = is_string($all[$key]) ? trim($all[$key]) : $all[$key];
            }
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file)) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    /**
     * Normalise a multi-file upload field into a list of single-file arrays.
     *
     * @return array<int,array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    public function fileList(string $key): array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || !isset($file['name'])) {
            return [];
        }
        if (!is_array($file['name'])) {
            return ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$file];
        }
        $out = [];
        foreach (array_keys($file['name']) as $i) {
            if (($file['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name'     => (string) $file['name'][$i],
                'type'     => (string) ($file['type'][$i] ?? ''),
                'tmp_name' => (string) ($file['tmp_name'][$i] ?? ''),
                'error'    => (int) ($file['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int) ($file['size'][$i] ?? 0),
            ];
        }
        return $out;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /** @return array<string,string> */
    public function params(): array
    {
        return $this->routeParams;
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('x-requested-with')) === 'xmlhttprequest';
    }

    public function expectsJson(): bool
    {
        return $this->isAjax()
            || str_contains(strtolower($this->header('accept')), 'application/json')
            || str_contains(strtolower($this->header('content-type')), 'application/json')
            || str_starts_with($this->path, '/api/');
    }

    public static function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $path = (string) ($_SERVER['REQUEST_URI'] ?? '');
        return str_contains($accept, 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_contains($path, '/api/');
    }

    public function userAgent(): string
    {
        return mb_substr($this->header('user-agent'), 0, 512);
    }

    public function referer(): string
    {
        return mb_substr($this->header('referer'), 0, 512);
    }

    /** Client IP, respecting configured trusted proxies only. */
    public static function clientIp(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $trusted = (array) Config::get('security.trusted_proxies', []);

        if ($trusted !== [] && in_array($remote, $trusted, true)) {
            $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($forwarded !== '') {
                $first = trim(explode(',', $forwarded)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                    return $first;
                }
            }
        }
        // Cloudflare sets this and strips client spoofing upstream.
        $cf = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) !== false) {
            return $cf;
        }
        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '0.0.0.0';
    }

    /** Coarse device classification for analytics (no fingerprinting). */
    public function deviceType(): string
    {
        $ua = strtolower($this->userAgent());
        if ($ua === '') {
            return 'unknown';
        }
        if (preg_match('/ipad|tablet|playbook|silk|kindle/', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobile|iphone|ipod|android.*mobile|windows phone|blackberry/', $ua)) {
            return 'mobile';
        }
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram|preview/', $ua)) {
            return 'bot';
        }
        return 'desktop';
    }

    public function browser(): string
    {
        $ua = $this->userAgent();
        $map = [
            'Edg'      => 'Edge',
            'OPR'      => 'Opera',
            'Chrome'   => 'Chrome',
            'CriOS'    => 'Chrome',
            'Firefox'  => 'Firefox',
            'FxiOS'    => 'Firefox',
            'Safari'   => 'Safari',
            'WhatsApp' => 'WhatsApp',
            'Telegram' => 'Telegram',
        ];
        foreach ($map as $needle => $name) {
            if (str_contains($ua, $needle)) {
                return $name;
            }
        }
        return 'Other';
    }
}
