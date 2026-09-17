<?php

declare(strict_types=1);

namespace App\Core;

/** String utilities, Unicode aware (Gujarati/Hindi safe). */
final class Str
{
    /**
     * URL-safe slug.
     *
     * Non-Latin scripts are transliterated when the intl extension is present
     * so a Gujarati title still yields a readable ASCII slug; otherwise the
     * non-ASCII parts are dropped and the caller falls back to a random code.
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('transliterator_transliterate')) {
            $converted = @transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[\'"`’]+/u', '', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9]+/u', $separator, $value) ?? '';
        $value = trim($value, $separator);
        $value = preg_replace('/' . preg_quote($separator, '/') . '{2,}/', $separator, $value) ?? $value;

        return $value;
    }

    /** Random, unambiguous short code (no O/0/I/1 confusion). */
    public static function shortCode(int $length = 6): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . $end;
    }

    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    public static function snake(string $value): string
    {
        $value = preg_replace('/\s+/u', '', $value) ?? $value;
        $value = preg_replace('/(.)(?=[A-Z])/u', '$1_', $value) ?? $value;
        return strtolower(str_replace('-', '_', $value));
    }

    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /** Strip everything that is not a plausible phone character. */
    public static function phone(string $value): string
    {
        return preg_replace('/[^0-9+]/', '', $value) ?? '';
    }

    /** Indian mobile normalised to the international form used by wa.me. */
    public static function whatsappNumber(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 10) {
            return '91' . $digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91' . substr($digits, 1);
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }
        if (strlen($digits) === 13 && str_starts_with($digits, '091')) {
            return substr($digits, 1);
        }
        return $digits;
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_contains($haystack, $needle);
    }

    /** Constant-time comparison for tokens and signatures. */
    public static function secureEquals(string $known, string $given): bool
    {
        return hash_equals($known, $given);
    }

    /** Mask a secret for display (ghp_abc...xyz). */
    public static function maskSecret(?string $secret, int $visible = 4): string
    {
        $secret = (string) $secret;
        if ($secret === '') {
            return '';
        }
        $len = strlen($secret);
        if ($len <= $visible * 2) {
            return str_repeat('•', $len);
        }
        return substr($secret, 0, $visible) . str_repeat('•', 8) . substr($secret, -$visible);
    }

    public static function bytesToHuman(int|float $bytes, int $decimals = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round((float) $bytes, $decimals) . ' ' . $units[$i];
    }

    /** Detect the dominant script so the right font can be chosen. */
    public static function detectScript(string $text): string
    {
        if (preg_match('/[\x{0A80}-\x{0AFF}]/u', $text)) {
            return 'gujarati';
        }
        if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
            return 'devanagari';
        }
        return 'latin';
    }
}
