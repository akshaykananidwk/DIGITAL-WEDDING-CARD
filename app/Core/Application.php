<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\SettingsService;

/**
 * The HTTP kernel.
 *
 * boot()  - configuration, session, locale, view globals
 * run()   - route, pipe through middleware, send the response
 */
final class Application
{
    private static ?self $instance = null;

    private Router $router;
    private bool $booted = false;

    /** @var array<string,class-string<Middleware\MiddlewareInterface>> */
    private array $middlewareAliases = [
        'installed'   => Middleware\EnsureInstalled::class,
        'maintenance' => Middleware\MaintenanceMode::class,
        'auth'        => Middleware\Authenticate::class,
        'guest'       => Middleware\RedirectIfAuthenticated::class,
        'admin'       => Middleware\AdminOnly::class,
        'can'         => Middleware\RequirePermission::class,
        'csrf'        => Middleware\VerifyCsrf::class,
        'throttle'    => Middleware\ThrottleRequests::class,
        'api'         => Middleware\ApiAuth::class,
    ];

    private function __construct()
    {
        $this->router = new Router();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        Config::load();

        if (Config::isInstalled()) {
            // Persisted settings can override configuration (admin editable).
            try {
                SettingsService::instance()->applyToConfig();
            } catch (\Throwable $e) {
                Logger::warning('Could not load settings from the database: ' . $e->getMessage());
            }
        }

        date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Kolkata'));

        if ((bool) Config::get('security.force_https', false) && !Url::isSecure() && PHP_SAPI !== 'cli') {
            $target = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
            Response::redirect($target, 301)->send();
            exit;
        }

        Session::start();
        Lang::boot();

        $this->shareViewGlobals();

        require APP_PATH . '/routes.php';
    }

    private function shareViewGlobals(): void
    {
        View::share('appName', (string) (setting('site_name') ?: Config::get('app.name')));
        View::share('appTagline', (string) (setting('site_tagline') ?: Config::get('app.tagline')));
        View::share('appVersion', Version::current());
        View::share('locale', Lang::locale());
        View::share('locales', Lang::available());
        View::share('authUser', Auth::user());
        View::share('isAdmin', Auth::isAdmin());
        View::share('flash', Session::takeFlash());
        View::share('router', $this->router);
    }

    public function run(): void
    {
        try {
            $this->boot();
            $request = Request::instance();
            $response = $this->handle($request);
        } catch (\Throwable $e) {
            ErrorHandler::handleException($e);
            return;
        }

        $response = $this->applySecurityHeaders($response, Request::instance());

        while (ob_get_level() > 1) {
            ob_end_clean();
        }
        if (ob_get_level() === 1) {
            ob_end_clean();
        }

        $response->send();

        Session::clearFormState();
    }

    public function handle(Request $request): Response
    {
        $route = $this->router->match($request->method(), $request->path());

        if ($route === null) {
            $allowed = $this->router->allowedMethods($request->path());
            if ($allowed !== []) {
                throw new HttpException(405, 'Method ' . $request->method() . ' is not allowed for this URL.');
            }
            throw HttpException::notFound();
        }

        $request->setRouteParams($route['params']);

        $pipeline = array_reverse($route['middleware']);
        $handler = static function (Request $request) use ($route): Response {
            return Application::getInstance()->callAction($route['handler'], $request);
        };

        foreach ($pipeline as $definition) {
            $next = $handler;
            $handler = function (Request $request) use ($definition, $next): Response {
                [$middleware, $parameters] = $this->resolveMiddleware($definition);
                return $middleware->handle($request, $next, $parameters);
            };
        }

        return $handler($request);
    }

    /** @return array{0:Middleware\MiddlewareInterface,1:array<int,string>} */
    private function resolveMiddleware(string $definition): array
    {
        $parameters = [];
        $name = $definition;
        if (str_contains($definition, ':')) {
            [$name, $paramString] = explode(':', $definition, 2);
            $parameters = array_map('trim', explode(',', $paramString));
        }
        $class = $this->middlewareAliases[$name] ?? null;
        if ($class === null || !class_exists($class)) {
            throw new \RuntimeException("Unknown middleware: {$name}");
        }
        /** @var Middleware\MiddlewareInterface $instance */
        $instance = new $class();
        return [$instance, $parameters];
    }

    /**
     * Invoke a route handler.
     *
     * Handlers are either [ControllerClass::class, 'method'] or a closure.
     */
    public function callAction(mixed $handler, Request $request): Response
    {
        if (is_callable($handler) && !is_array($handler)) {
            $result = $handler($request);
            return $this->toResponse($result);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            if (!class_exists($class)) {
                throw new \RuntimeException("Controller not found: {$class}");
            }
            $controller = new $class();
            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Controller action not found: {$class}::{$method}");
            }
            $result = $controller->{$method}($request);
            return $this->toResponse($result);
        }

        throw new \RuntimeException('Invalid route handler.');
    }

    private function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if ($result instanceof View) {
            return $result->toResponse();
        }
        if (is_string($result)) {
            return Response::html($result);
        }
        if (is_array($result) || $result instanceof \JsonSerializable) {
            return Response::json($result);
        }
        if ($result === null) {
            return Response::noContent();
        }
        throw new \RuntimeException('Route handler returned an unsupported value.');
    }

    /**
     * Security headers, including a CSP tight enough to stop injected scripts
     * but wide enough for inline invitation styles (templates are authored as
     * HTML/CSS by administrators).
     */
    private function applySecurityHeaders(Response $response, Request $request): Response
    {
        // Some hosts cannot unset this in Apache config, so do it here too:
        // the PHP version is free reconnaissance for an attacker.
        header_remove('X-Powered-By');

        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('X-Frame-Options', str_starts_with($request->path(), '/invite') ? 'ALLOWALL' : 'SAMEORIGIN');
        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');

        if (Url::isSecure()) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ((bool) Config::get('security.csp_enabled', true)) {
            $nonce = Csp::nonce();
            $csp = implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "frame-ancestors 'self'",
                "form-action 'self'",
                "img-src 'self' data: blob: https:",
                "media-src 'self' blob: https:",
                "font-src 'self' data:",
                // Invitation templates carry their own inline CSS.
                "style-src 'self' 'unsafe-inline'",
                "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-inline' https:",
                "connect-src 'self'",
                "worker-src 'self'",
            ]);
            $header = (bool) Config::get('security.csp_report_only', false)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';
            $response->header($header, $csp);
        }

        return $response;
    }
}
