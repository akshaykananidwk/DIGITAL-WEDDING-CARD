<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Authenticated symmetric encryption for secrets at rest (GitHub token, SMTP
 * password, Gemini key).
 *
 * AES-256-GCM with a random nonce; the key is the application key created by
 * the installer and stored outside the repository.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'enc:v1:';

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    private static function key(): string
    {
        $raw = (string) Config::get('app.key', '');
        if ($raw === '') {
            throw new \RuntimeException('Application key is not configured.');
        }
        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), true);
            if ($decoded === false || strlen($decoded) < 32) {
                throw new \RuntimeException('Application key is malformed.');
            }
            return substr($decoded, 0, 32);
        }
        // Non-base64 keys are stretched so any legacy value still works.
        return hash('sha256', $raw, true);
    }

    public static function encrypt(?string $plain): string
    {
        if ($plain === null || $plain === '') {
            return '';
        }
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return self::PREFIX . base64_encode($nonce . $tag . $cipher);
    }

    public static function decrypt(?string $payload): string
    {
        $payload = (string) $payload;
        if ($payload === '') {
            return '';
        }
        if (!str_starts_with($payload, self::PREFIX)) {
            // Value stored before encryption was enabled - return as is.
            return $payload;
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $nonce = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($plain === false) {
            Logger::warning('Failed to decrypt a stored secret (wrong application key?).');
            return '';
        }
        return $plain;
    }

    public static function isEncrypted(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    /** Keyed hash for lookup columns that must not store the plain value. */
    public static function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }
}
