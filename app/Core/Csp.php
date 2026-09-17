<?php

declare(strict_types=1);

namespace App\Core;

/** Per-request nonce so our own inline bootstrap scripts stay allowed. */
final class Csp
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        return self::$nonce ??= base64_encode(random_bytes(16));
    }

    public static function attribute(): string
    {
        return ' nonce="' . e(self::nonce()) . '"';
    }
}
