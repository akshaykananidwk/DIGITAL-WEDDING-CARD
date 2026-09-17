<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Regex router with named parameters and per-route middleware.
 *
 * Routes are declared in app/routes.php. Static routes are matched by a hash
 * lookup first, so the routing table stays O(1) for the common case.
 */
final class Router
{
    /** @var array<string,array<string,array{handler:mixed,middleware:array<int,string>,name:string}>> */
    private array $static = [];

    /** @var array<string,array<int,array{regex:string,params:array<int,string>,handler:mixed,middleware:array<int,string>,name:string}>> */
    private array $dynamic = [];

    /** @var array<string,string> name => path pattern */
    private array $names = [];

    /** @var array<int,string> */
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    public function get(string $path, mixed $handler, array $middleware = [], string $name = ''): self
    {
        return $this->add('GET', $path, $handler, $middleware, $name);
    }

    public function post(string $path, mixed $handler, array $middleware = [], string $name = ''): self
    {
        return $this->add('POST', $path, $handler, $middleware, $name);
    }

    public function put(string $path, mixed $handler, array $middleware = [], string $name = ''): self
    {
        return $this->add('PUT', $path, $handler, $middleware, $name);
    }

    public function patch(string $path, mixed $handler, array $middleware = [], string $name = ''): self
    {
        return $this->add('PATCH', $path, $handler, $middleware, $name);
    }

    public function delete(string $path, mixed $handler, array $middleware = [], string $name = ''): self
    {
        return $this->add('DELETE', $path, $handler, $middleware, $name);
    }

    /** Register the same handler for GET and POST (simple forms). */
    public function any(array $methods, string $path, mixed $handler, array $middleware = [], string $name = ''): self
    {
        foreach ($methods as $method) {
            $this->add(strtoupper($method), $path, $handler, $middleware, $name);
            $name = ''; // only name it once
        }
        return $this;
    }

    /** Group routes under a shared prefix and middleware stack. */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = rtrim($previousPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    private function add(string $method, string $path, mixed $handler, array $middleware, string $name): self
    {
        $path = '/' . trim($this->groupPrefix . '/' . trim($path, '/'), '/');
        if ($path === '/') {
            $path = '/';
        }
        $middleware = array_merge($this->groupMiddleware, $middleware);

        if ($name !== '') {
            $this->names[$name] = $path;
        }

        if (!str_contains($path, '{')) {
            $this->static[$method][$path] = compact('handler', 'middleware', 'name');
            return $this;
        }

        $params = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                $pattern = $m[2] ?? '[^/]+';
                return '(' . $pattern . ')';
            },
            $path
        ) ?? $path;

        $this->dynamic[$method][] = [
            'regex'      => '#^' . $regex . '$#u',
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $middleware,
            'name'       => $name,
        ];

        return $this;
    }

    /**
     * @return array{handler:mixed,middleware:array<int,string>,params:array<string,string>,name:string}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        if (isset($this->static[$method][$path])) {
            $route = $this->static[$method][$path];
            return [
                'handler'    => $route['handler'],
                'middleware' => $route['middleware'],
                'params'     => [],
                'name'       => $route['name'],
            ];
        }

        foreach ($this->dynamic[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches) === 1) {
                array_shift($matches);
                $params = [];
                foreach ($route['params'] as $i => $param) {
                    $params[$param] = $matches[$i] ?? '';
                }
                return [
                    'handler'    => $route['handler'],
                    'middleware' => $route['middleware'],
                    'params'     => $params,
                    'name'       => $route['name'],
                ];
            }
        }

        return null;
    }

    /** Methods that *would* match this path - for a correct 405 response. */
    public function allowedMethods(string $path): array
    {
        $allowed = [];
        foreach (array_keys($this->static) as $method) {
            if (isset($this->static[$method][$path])) {
                $allowed[] = $method;
            }
        }
        foreach ($this->dynamic as $method => $routes) {
            foreach ($routes as $route) {
                if (preg_match($route['regex'], $path) === 1) {
                    $allowed[] = $method;
                    break;
                }
            }
        }
        return array_values(array_unique($allowed));
    }

    /** Build a URL from a route name. */
    public function route(string $name, array $params = []): string
    {
        $path = $this->names[$name] ?? null;
        if ($path === null) {
            throw new \InvalidArgumentException("Unknown route name: {$name}");
        }
        foreach ($params as $key => $value) {
            $path = preg_replace('/\{' . preg_quote((string) $key, '/') . '(?::[^}]+)?\}/', rawurlencode((string) $value), $path) ?? $path;
        }
        return Url::to($path);
    }

    public function routeCount(): int
    {
        $count = 0;
        foreach ($this->static as $routes) {
            $count += count($routes);
        }
        foreach ($this->dynamic as $routes) {
            $count += count($routes);
        }
        return $count;
    }
}
