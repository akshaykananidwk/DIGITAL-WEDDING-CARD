<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Plain-PHP template renderer with layouts and sections.
 *
 * No template language to learn, no compilation step, and `e()` everywhere
 * means output is escaped by default in review.
 */
final class View
{
    /** @var array<string,mixed> shared with every view */
    private static array $shared = [];

    /** @var array<string,string> */
    private array $sections = [];
    private ?string $layout = null;
    private array $layoutData = [];

    private function __construct(
        private readonly string $view,
        private array $data = []
    ) {
    }

    public static function make(string $view, array $data = []): self
    {
        return new self($view, $data);
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(): array
    {
        return self::$shared;
    }

    /** Render to a string. */
    public function render(): string
    {
        $path = self::resolve($this->view);
        $content = $this->capture($path, array_merge(self::$shared, $this->data));

        if ($this->layout !== null) {
            $layoutPath = self::resolve($this->layout);
            $layoutData = array_merge(
                self::$shared,
                $this->data,
                $this->layoutData,
                ['content' => $content, '__sections' => $this->sections]
            );
            return $this->capture($layoutPath, $layoutData);
        }

        return $content;
    }

    public function toResponse(int $status = 200): Response
    {
        return Response::html($this->render(), $status);
    }

    private function capture(string $path, array $data): string
    {
        $view = $this;
        ob_start();
        try {
            // Views receive their data as local variables plus $view for
            // layout/section helpers.
            (static function (string $__path, array $__data, View $view): void {
                extract($__data, EXTR_SKIP);
                require $__path;
            })($path, $data, $view);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function resolve(string $view): string
    {
        $clean = str_replace(['..', "\0"], '', $view);
        $clean = str_replace('.', '/', $clean);
        $path = APP_PATH . '/Views/' . ltrim($clean, '/') . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        return $path;
    }

    public static function exists(string $view): bool
    {
        try {
            self::resolve($view);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ---------------- helpers available inside a view ----------------

    /** Declare the layout this view extends. */
    public function extend(string $layout, array $data = []): void
    {
        $this->layout = $layout;
        $this->layoutData = $data;
    }

    /** Start capturing a named section. */
    public function start(string $name): void
    {
        $this->sections['__current'] = $name;
        ob_start();
    }

    /** Finish the current section. */
    public function stop(): void
    {
        $name = $this->sections['__current'] ?? null;
        unset($this->sections['__current']);
        $content = (string) ob_get_clean();
        if ($name !== null) {
            $this->sections[$name] = ($this->sections[$name] ?? '') . $content;
        }
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    /** Render a partial inline. */
    public function include(string $view, array $data = []): void
    {
        $path = self::resolve($view);
        echo $this->capture($path, array_merge(self::$shared, $this->data, $data));
    }

    /** Render a partial only if it exists (template layouts). */
    public function includeIf(string $view, array $data = []): bool
    {
        if (!self::exists($view)) {
            return false;
        }
        $this->include($view, $data);
        return true;
    }

    public function data(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? self::$shared[$key] ?? $default;
    }

    public function with(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }
}
