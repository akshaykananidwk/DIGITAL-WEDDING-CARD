<?php

declare(strict_types=1);

namespace App\Core\Qr;

/**
 * Reed-Solomon error correction over GF(256) with the QR primitive
 * polynomial x^8 + x^4 + x^3 + x^2 + 1 (0x11D).
 */
final class ReedSolomon
{
    /** @var array<int,array<int,int>> cached generator polynomials by degree */
    private static array $generators = [];

    /** Multiply two field elements. */
    public static function multiply(int $a, int $b): int
    {
        $a &= 0xFF;
        $b &= 0xFF;
        $result = 0;
        for ($i = 7; $i >= 0; $i--) {
            // Russian peasant multiplication with reduction by 0x11D.
            $result = ($result << 1) ^ (($result >> 7) * 0x11D);
            $result ^= (($b >> $i) & 1) * $a;
        }
        return $result & 0xFF;
    }

    /**
     * Divisor polynomial of the given degree.
     *
     * @return array<int,int> coefficients, highest power omitted (monic)
     */
    public static function generator(int $degree): array
    {
        if (isset(self::$generators[$degree])) {
            return self::$generators[$degree];
        }
        if ($degree < 1 || $degree > 255) {
            throw new \InvalidArgumentException('Invalid generator degree: ' . $degree);
        }

        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1; // the polynomial "1"

        // Multiply by (x - r^i) for i = 0 .. degree-1, where r = 0x02.
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::multiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::multiply($root, 0x02);
        }

        return self::$generators[$degree] = $result;
    }

    /**
     * Remainder of data divided by the generator - the ECC codewords.
     *
     * @param array<int,int> $data
     * @param array<int,int> $generator
     * @return array<int,int>
     */
    public static function remainder(array $data, array $generator): array
    {
        $degree = count($generator);
        $result = array_fill(0, $degree, 0);

        foreach ($data as $byte) {
            $factor = ($byte ^ $result[0]) & 0xFF;
            array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $degree; $i++) {
                $result[$i] ^= self::multiply($generator[$i], $factor);
            }
        }

        return $result;
    }
}
