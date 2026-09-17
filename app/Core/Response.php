<?php

declare(strict_types=1);

namespace App\Core;

/** HTTP response value object. Nothing is sent until send() is called. */
final class Response
{
    private string $content = '';
    private int $status = 200;
    /** @var array<string,string> */
    private array $headers = [];
    /** @var callable|null */
    private $streamer = null;

    public static function make(string $content = '', int $status = 200, array $headers = []): self
    {
        $response = new self();
        $response->content = $content;
        $response->status = $status;
        foreach ($headers as $key => $value) {
            $response->header((string) $key, (string) $value);
        }
        return $response;
    }

    public static function html(string $html, int $status = 200): self
    {
        return self::make($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $text, int $status = 200): self
    {
        return self::make($text, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function xml(string $xml, int $status = 200): self
    {
        return self::make($xml, $status, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return self::make(
            $body === false ? '{"success":false,"message":"Encoding error"}' : $body,
            $status,
            $headers + ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    /** Standard success envelope used by every /api/v1 endpoint. */
    public static function apiSuccess(mixed $data = null, string $message = '', array $meta = []): self
    {
        $payload = ['success' => true];
        if ($message !== '') {
            $payload['message'] = $message;
        }
        if ($data !== null) {
            $payload['data'] = $data;
        }
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        return self::json($payload);
    }

    public static function apiError(string $message, int $status = 422, array $errors = []): self
    {
        $payload = ['success' => false, 'message' => $message];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }
        return self::json($payload, $status);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return self::make('', $status, ['Location' => $to]);
    }

    public static function noContent(): self
    {
        return self::make('', 204);
    }

    /** Inline or attachment file download from a string of bytes. */
    public static function download(
        string $bytes,
        string $filename,
        string $contentType = 'application/octet-stream',
        bool $inline = false
    ): self {
        $safe = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $filename) ?: 'download';
        return self::make($bytes, 200, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment')
                . '; filename="' . $safe . '"'
                . "; filename*=UTF-8''" . rawurlencode($filename),
            'Content-Length'      => (string) strlen($bytes),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Stream a large file from disk without loading it into memory. */
    public static function file(string $path, string $filename, string $contentType, bool $inline = false): self
    {
        $response = new self();
        $response->status = 200;
        $safe = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $filename) ?: 'download';
        $response->headers = [
            'Content-Type'        => $contentType,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $safe . '"',
            'Content-Length'      => (string) filesize($path),
            'X-Content-Type-Options' => 'nosniff',
        ];
        $response->streamer = static function () use ($path): void {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return;
            }
            while (!feof($handle)) {
                echo fread($handle, 262144);
                flush();
            }
            fclose($handle);
        };
        return $response;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /** @return array<string,string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function cache(int $seconds): self
    {
        if ($seconds <= 0) {
            return $this->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->header('Pragma', 'no-cache');
        }
        return $this->header('Cache-Control', 'public, max-age=' . $seconds)
            ->header('Expires', gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        if ($this->streamer !== null) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            ($this->streamer)();
            return;
        }

        echo $this->content;
    }
}
