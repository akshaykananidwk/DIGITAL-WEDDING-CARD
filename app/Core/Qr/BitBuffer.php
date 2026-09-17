<?php

declare(strict_types=1);

namespace App\Core\Qr;

/** Append-only bit stream used while building a QR payload. */
final class BitBuffer
{
    /** @var array<int,int> individual bits, 0 or 1 */
    private array $bits = [];

    public function append(int $value, int $length): void
    {
        if ($length < 0 || $length > 32) {
            throw new \InvalidArgumentException('Invalid bit length: ' . $length);
        }
        for ($i = $length - 1; $i >= 0; $i--) {
            $this->bits[] = ($value >> $i) & 1;
        }
    }

    public function length(): int
    {
        return count($this->bits);
    }

    /** @return array<int,int> bytes, zero padded on the right */
    public function toBytes(): array
    {
        $bytes = [];
        $count = count($this->bits);
        for ($i = 0; $i < $count; $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | ($this->bits[$i + $j] ?? 0);
            }
            $bytes[] = $byte;
        }
        return $bytes;
    }
}
