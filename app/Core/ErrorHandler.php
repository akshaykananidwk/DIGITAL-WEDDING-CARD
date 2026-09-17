<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Converts PHP notices/warnings into exceptions and renders a safe error page.
 *
 * Production never shows a stack trace: users get a friendly message with a
 * reference id, administrators find the detail in storage/logs.
 */
final class ErrorHandler
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '0'); // we handle logging ourselves

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }
        // Deprecations should never take a production page down.
        if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
            Logger::warning('PHP deprecation: ' . $message, ['file' => $file, 'line' => $line]);
            return true;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(\Throwable $e): void
    {
        $reference = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        Logger::critical($e::class . ': ' . $e->getMessage(), [
            'reference' => $reference,
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => self::compactTrace($e),
        ]);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;

        if (Request::wantsJson()) {
            self::sendJson($status, $e, $reference);
            return;
        }

        self::sendHtml($status, $e, $reference);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }
        if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }
        self::handleException(new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        ));
    }

    private static function compactTrace(\Throwable $e): array
    {
        $out = [];
        foreach (array_slice($e->getTrace(), 0, 15) as $frame) {
            $out[] = sprintf(
                '%s%s%s() at %s:%d',
                $frame['class'] ?? '',
                isset($frame['class']) ? ($frame['type'] ?? '::') : '',
                $frame['function'] ?? '{closure}',
                $frame['file'] ?? 'internal',
                $frame['line'] ?? 0
            );
        }
        return $out;
    }

    private static function sendJson(int $status, \Throwable $e, string $reference): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $payload = [
            'success'   => false,
            'message'   => $status < 500 ? $e->getMessage() : 'Something went wrong. Please try again.',
            'reference' => $reference,
        ];
        if (Config::get('app.debug', false) && $status >= 500) {
            $payload['debug'] = [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => self::compactTrace($e),
            ];
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    private static function sendHtml(int $status, \Throwable $e, string $reference): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');

        $debug = (bool) Config::get('app.debug', false);
        $view = APP_PATH . '/Views/errors/' . ($status === 404 ? '404' : ($status === 403 ? '403' : '500')) . '.php';

        // Try the styled error page; fall back to inline HTML if views or the
        // database are unavailable (which is likely during a failure).
        if (is_file($view)) {
            try {
                (static function () use ($view, $e, $reference, $status, $debug): void {
                    $exception = $e;
                    $errorReference = $reference;
                    $statusCode = $status;
                    $showDebug = $debug;
                    require $view;
                })();
                return;
            } catch (\Throwable) {
                // fall through to the minimal page
            }
        }

        $message = $status === 404
            ? 'The page you are looking for could not be found.'
            : 'Something went wrong. Please try again.';

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Error ' . (int) $status . '</title>'
            . '<style>body{font-family:system-ui,sans-serif;background:#faf7f2;color:#3d2b1f;'
            . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:1.5rem}'
            . '.box{max-width:32rem;text-align:center}h1{font-size:3rem;margin:0}'
            . 'code{background:#efe7db;padding:.2rem .4rem;border-radius:.3rem;font-size:.85rem}'
            . 'pre{text-align:left;background:#1d1b19;color:#f0e6d2;padding:1rem;border-radius:.5rem;overflow:auto}'
            . '</style></head><body><div class="box"><h1>' . (int) $status . '</h1><p>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><small>Reference: <code>' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</code></small></p>';

        if ($debug) {
            echo '<pre>' . htmlspecialchars(
                $e::class . ': ' . $e->getMessage() . "\n"
                . $e->getFile() . ':' . $e->getLine() . "\n\n"
                . implode("\n", self::compactTrace($e)),
                ENT_QUOTES,
                'UTF-8'
            ) . '</pre>';
        }

        echo '</div></body></html>';
    }
}
