<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Url;
use App\Core\View;

/**
 * Outbound email: verification, password reset and admin notices.
 *
 * Three drivers, all dependency free:
 *   mail  - PHP's mail(), which is what most cPanel hosts provide
 *   smtp  - a small SMTP client over a socket, with STARTTLS/implicit TLS
 *   log   - writes the message to storage/logs, used in development
 */
final class MailService
{
    public function __construct(
        private readonly SettingsService $settings = new SettingsService()
    ) {
    }

    public static function instance(): self
    {
        return new self(SettingsService::instance());
    }

    /**
     * Send an HTML email (a plain-text part is derived automatically).
     *
     * @return array{ok:bool,message:string}
     */
    public function send(string $toEmail, string $toName, string $subject, string $html): array
    {
        $toEmail = trim($toEmail);
        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'message' => 'Invalid recipient address.'];
        }

        $driver = (string) Config::get('mail.driver', 'mail');
        $fromAddress = (string) (Config::get('mail.from_address') ?: 'no-reply@' . $this->hostname());
        $fromName = (string) (Config::get('mail.from_name') ?: Config::get('app.name', 'Invitations'));

        // Header injection guard: nothing user-supplied may contain CR/LF.
        $subject = $this->sanitiseHeader($subject);
        $toName = $this->sanitiseHeader($toName);
        $fromName = $this->sanitiseHeader($fromName);

        try {
            return match ($driver) {
                'smtp' => $this->sendSmtp($toEmail, $toName, $subject, $html, $fromAddress, $fromName),
                'log'  => $this->sendLog($toEmail, $subject, $html),
                default => $this->sendMail($toEmail, $toName, $subject, $html, $fromAddress, $fromName),
            };
        } catch (\Throwable $e) {
            Logger::error('Email send failed: ' . $e->getMessage(), ['driver' => $driver], Logger::MAIL);
            return ['ok' => false, 'message' => 'The email could not be sent.'];
        }
    }

    /** Render one of the templates in app/Views/emails and send it. */
    public function sendTemplate(string $toEmail, string $toName, string $subject, string $view, array $data = []): array
    {
        $html = View::make('emails.' . $view, array_merge($data, [
            'subject'  => $subject,
            'toName'   => $toName,
            'appName'  => (string) (setting('site_name') ?: Config::get('app.name')),
            'appUrl'   => Url::base(),
        ]))->render();

        return $this->send($toEmail, $toName, $subject, $html);
    }

    private function sendMail(
        string $toEmail,
        string $toName,
        string $subject,
        string $html,
        string $fromAddress,
        string $fromName
    ): array {
        if (!function_exists('mail')) {
            return ['ok' => false, 'message' => 'This server cannot send email with the PHP mail driver.'];
        }

        $boundary = '=_' . bin2hex(random_bytes(12));
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . $this->formatAddress($fromName, $fromAddress),
            'Reply-To: ' . $this->formatAddress($fromName, $fromAddress),
            'X-Mailer: ShubhKankotri',
        ];

        $body = $this->multipartBody($html, $boundary);
        $to = $toName !== '' ? $this->formatAddress($toName, $toEmail) : $toEmail;

        $sent = @mail(
            $to,
            $this->encodeHeaderValue($subject),
            $body,
            implode("\r\n", $headers),
            '-f' . $fromAddress
        );

        if ($sent) {
            Logger::info('Email sent via mail()', ['subject' => $subject], Logger::MAIL);
            return ['ok' => true, 'message' => 'Email sent.'];
        }
        Logger::warning('mail() returned false', ['subject' => $subject], Logger::MAIL);
        return ['ok' => false, 'message' => 'The server rejected the email.'];
    }

    private function sendLog(string $toEmail, string $subject, string $html): array
    {
        Logger::info('Email (log driver)', [
            'to'      => $toEmail,
            'subject' => $subject,
            'body'    => mb_substr(strip_tags($html), 0, 1500),
        ], Logger::MAIL);
        return ['ok' => true, 'message' => 'Email written to the log (log driver).'];
    }

    /**
     * Minimal SMTP client.
     *
     * Enough for the transactional mail this application sends, with AUTH
     * LOGIN/PLAIN, STARTTLS and implicit TLS.
     */
    private function sendSmtp(
        string $toEmail,
        string $toName,
        string $subject,
        string $html,
        string $fromAddress,
        string $fromName
    ): array {
        $host = (string) Config::get('mail.smtp.host', '');
        $port = (int) Config::get('mail.smtp.port', 587);
        $username = (string) Config::get('mail.smtp.username', '');
        $password = (string) Config::get('mail.smtp.password', '');
        $encryption = strtolower((string) Config::get('mail.smtp.encryption', 'tls'));
        $timeout = max(5, (int) Config::get('mail.smtp.timeout', 15));

        if ($host === '') {
            return ['ok' => false, 'message' => 'SMTP host is not configured.'];
        }

        $transport = $encryption === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errorCode,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
        );
        if ($socket === false) {
            return ['ok' => false, 'message' => 'Could not connect to the SMTP server (' . $errorCode . ').'];
        }
        stream_set_timeout($socket, $timeout);

        $read = static function () use ($socket): string {
            $response = '';
            while (($line = fgets($socket, 515)) !== false) {
                $response .= $line;
                // A multi-line reply has a hyphen in the fourth character.
                if (strlen($line) < 4 || $line[3] !== '-') {
                    break;
                }
            }
            return $response;
        };
        $write = static function (string $command) use ($socket): void {
            fwrite($socket, $command . "\r\n");
        };
        $expect = static function (string $response, array $codes): bool {
            $code = (int) substr(trim($response), 0, 3);
            return in_array($code, $codes, true);
        };

        try {
            if (!$expect($read(), [220])) {
                throw new \RuntimeException('The SMTP server did not greet us.');
            }

            $hostname = $this->hostname();
            $write('EHLO ' . $hostname);
            $greeting = $read();
            if (!$expect($greeting, [250])) {
                throw new \RuntimeException('EHLO was rejected.');
            }

            if ($encryption === 'tls') {
                $write('STARTTLS');
                if (!$expect($read(), [220])) {
                    throw new \RuntimeException('STARTTLS was refused.');
                }
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('TLS negotiation failed.');
                }
                $write('EHLO ' . $hostname);
                $greeting = $read();
                if (!$expect($greeting, [250])) {
                    throw new \RuntimeException('EHLO after STARTTLS was rejected.');
                }
            }

            if ($username !== '') {
                if (stripos($greeting, 'AUTH') !== false && stripos($greeting, 'PLAIN') !== false) {
                    $write('AUTH PLAIN ' . base64_encode("\0" . $username . "\0" . $password));
                    if (!$expect($read(), [235])) {
                        throw new \RuntimeException('SMTP authentication failed.');
                    }
                } else {
                    $write('AUTH LOGIN');
                    if (!$expect($read(), [334])) {
                        throw new \RuntimeException('The SMTP server refused AUTH LOGIN.');
                    }
                    $write(base64_encode($username));
                    if (!$expect($read(), [334])) {
                        throw new \RuntimeException('SMTP username was rejected.');
                    }
                    $write(base64_encode($password));
                    if (!$expect($read(), [235])) {
                        throw new \RuntimeException('SMTP authentication failed.');
                    }
                }
            }

            $write('MAIL FROM:<' . $fromAddress . '>');
            if (!$expect($read(), [250])) {
                throw new \RuntimeException('The sender address was rejected.');
            }
            $write('RCPT TO:<' . $toEmail . '>');
            if (!$expect($read(), [250, 251])) {
                throw new \RuntimeException('The recipient address was rejected.');
            }
            $write('DATA');
            if (!$expect($read(), [354])) {
                throw new \RuntimeException('The server refused the message body.');
            }

            $boundary = '=_' . bin2hex(random_bytes(12));
            $message = implode("\r\n", [
                'Date: ' . date('r'),
                'From: ' . $this->formatAddress($fromName, $fromAddress),
                'To: ' . ($toName !== '' ? $this->formatAddress($toName, $toEmail) : $toEmail),
                'Subject: ' . $this->encodeHeaderValue($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $hostname . '>',
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                '',
                $this->multipartBody($html, $boundary),
            ]);
            // Dot-stuffing, per RFC 5321.
            $message = preg_replace('/^\./m', '..', $message) ?? $message;
            $write($message);
            $write('.');
            if (!$expect($read(), [250])) {
                throw new \RuntimeException('The server did not accept the message.');
            }

            $write('QUIT');
            fclose($socket);

            Logger::info('Email sent via SMTP', ['subject' => $subject], Logger::MAIL);
            return ['ok' => true, 'message' => 'Email sent.'];
        } catch (\Throwable $e) {
            if (is_resource($socket)) {
                @fwrite($socket, "QUIT\r\n");
                @fclose($socket);
            }
            Logger::error('SMTP error: ' . $e->getMessage(), [], Logger::MAIL);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function multipartBody(string $html, string $boundary): string
    {
        $text = $this->htmlToText($html);
        return implode("\r\n", [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($text), 76, "\r\n"),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($html), 76, "\r\n"),
            '--' . $boundary . '--',
            '',
        ]);
    }

    private function htmlToText(string $html): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $text = preg_replace('#</(p|div|tr|h[1-6])>#i', "\n\n", $text) ?? $text;
        // Keep link targets visible in the plain-text part.
        $text = preg_replace('#<a[^>]+href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    private function formatAddress(string $name, string $email): string
    {
        if ($name === '') {
            return $email;
        }
        return $this->encodeHeaderValue($name) . ' <' . $email . '>';
    }

    /** RFC 2047 encoding, so Gujarati subjects arrive intact. */
    private function encodeHeaderValue(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function sanitiseHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0", '%0a', '%0d'], '', $value));
    }

    private function hostname(): string
    {
        $host = parse_url(Url::base(), PHP_URL_HOST);
        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    /** Admin "send test email" action. */
    public function sendTest(string $toEmail): array
    {
        return $this->sendTemplate(
            $toEmail,
            'Administrator',
            'Test email from ' . (string) (setting('site_name') ?: Config::get('app.name')),
            'test',
            ['driver' => (string) Config::get('mail.driver', 'mail')]
        );
    }
}
