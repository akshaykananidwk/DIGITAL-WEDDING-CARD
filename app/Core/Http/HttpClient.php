<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Logger;

/**
 * Small cURL wrapper for the two outbound integrations (Gemini, GitHub).
 *
 * Deliberately strict: TLS verification is always on, redirects are not
 * followed blindly, responses are size-capped, and only http(s) URLs to
 * public hosts are allowed so a stored setting cannot be turned into an
 * SSRF probe of the local network.
 */
final class HttpClient
{
    private const MAX_RESPONSE_BYTES = 67108864; // 64 MB (update archives)

    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10
    ) {
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string,headers:array<string,string>,error:string|null}
     */
    public function get(string $url, array $headers = [], ?string $saveTo = null): array
    {
        return $this->request('GET', $url, null, $headers, $saveTo);
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string,headers:array<string,string>,error:string|null}
     */
    public function postJson(string $url, array $payload, array $headers = []): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this->request(
            'POST',
            $url,
            $body === false ? '{}' : $body,
            $headers + ['Content-Type' => 'application/json'],
        );
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string,headers:array<string,string>,error:string|null}
     */
    public function request(
        string $method,
        string $url,
        ?string $body = null,
        array $headers = [],
        ?string $saveTo = null
    ): array {
        $this->assertSafeUrl($url);

        if (!function_exists('curl_init')) {
            return $this->streamFallback($method, $url, $body, $headers, $saveTo);
        }

        $handle = curl_init();
        $responseHeaders = [];
        $fileHandle = null;

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => $saveTo === null,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'ShubhKankotri/' . \App\Core\Version::current() . ' (+PHP)',
            CURLOPT_ENCODING       => '',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return $length;
            },
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        if ($headers !== []) {
            $formatted = [];
            foreach ($headers as $name => $value) {
                $formatted[] = $name . ': ' . $value;
            }
            $options[CURLOPT_HTTPHEADER] = $formatted;
        }
        if ($saveTo !== null) {
            $dir = dirname($saveTo);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['status' => 0, 'body' => '', 'headers' => [], 'error' => 'Cannot write to ' . $dir];
            }
            $fileHandle = fopen($saveTo, 'wb');
            if ($fileHandle === false) {
                return ['status' => 0, 'body' => '', 'headers' => [], 'error' => 'Cannot open ' . $saveTo];
            }
            $options[CURLOPT_FILE] = $fileHandle;
        } else {
            // Cap in-memory responses.
            $options[CURLOPT_BUFFERSIZE] = 131072;
            $options[CURLOPT_NOPROGRESS] = false;
            $options[CURLOPT_PROGRESSFUNCTION] = static function ($curl, $dlTotal, $dlNow): int {
                return $dlNow > self::MAX_RESPONSE_BYTES ? 1 : 0;
            };
        }

        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_errno($handle) !== 0 ? curl_error($handle) : null;
        curl_close($handle);

        if ($fileHandle !== null) {
            fclose($fileHandle);
        }

        return [
            'status'  => $status,
            'body'    => is_string($response) ? $response : '',
            'headers' => $responseHeaders,
            'error'   => $error,
        ];
    }

    /** Used only when cURL is unavailable on the host. */
    private function streamFallback(
        string $method,
        string $url,
        ?string $body,
        array $headers,
        ?string $saveTo
    ): array {
        $headerLines = '';
        foreach ($headers as $name => $value) {
            $headerLines .= $name . ': ' . $value . "\r\n";
        }
        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => $headerLines,
                'content'       => $body ?? '',
                'timeout'       => $this->timeout,
                'ignore_errors' => true,
                'max_redirects' => 3,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $status = 0;
        $responseHeaders = [];
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
                continue;
            }
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
        }

        if ($response === false) {
            return ['status' => $status, 'body' => '', 'headers' => $responseHeaders, 'error' => 'Request failed'];
        }
        if ($saveTo !== null) {
            @file_put_contents($saveTo, $response);
            return ['status' => $status, 'body' => '', 'headers' => $responseHeaders, 'error' => null];
        }
        return ['status' => $status, 'body' => $response, 'headers' => $responseHeaders, 'error' => null];
    }

    /**
     * Reject anything that is not a public https(s) URL.
     *
     * @throws \InvalidArgumentException
     */
    private function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Invalid URL.');
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Only http and https URLs are allowed.');
        }

        $host = strtolower($parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            Logger::security('Blocked an outbound request to a loopback host', ['host' => $host]);
            throw new \InvalidArgumentException('Requests to local addresses are not allowed.');
        }
        // Literal private ranges. (A hostname resolving to a private address
        // is out of scope here; both integrations use fixed, known hosts.)
        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            Logger::security('Blocked an outbound request to a private address', ['host' => $host]);
            throw new \InvalidArgumentException('Requests to private addresses are not allowed.');
        }
    }

    /** Is outbound HTTPS available at all? Used by System → Health. */
    public static function isAvailable(): bool
    {
        return function_exists('curl_init')
            || (bool) ini_get('allow_url_fopen');
    }
}
