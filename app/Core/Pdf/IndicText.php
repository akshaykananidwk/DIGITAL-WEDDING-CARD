<?php

declare(strict_types=1);

namespace App\Core\Pdf;

/**
 * Light Indic text handling for PDF output.
 *
 * A full OpenType shaper is out of scope for a dependency-free PHP app, but
 * one transformation matters more than all the others for Gujarati, Hindi and
 * other Indic scripts: the pre-base vowel sign (ि / િ) is *typed* after its
 * consonant and *drawn* before it. Without that reorder the text is wrong;
 * with it, ordinary invitation wording renders correctly.
 *
 * Conjuncts are rendered as consonant + visible virama + consonant rather
 * than as a ligature, which is legible and unambiguous. See docs/PDF.md.
 */
final class IndicText
{
    /** Pre-base vowel signs that must move before their consonant cluster. */
    private const PRE_BASE_MATRAS = [
        0x0ABF, // GUJARATI VOWEL SIGN I
        0x093F, // DEVANAGARI VOWEL SIGN I
        0x0A3F, // GURMUKHI VOWEL SIGN I
        0x0B47, // ORIYA VOWEL SIGN E
        0x0BC6, 0x0BC7, 0x0BC8, // TAMIL E/EE/AI
        0x0C46, 0x0C47, 0x0C48, // TELUGU
        0x0D46, 0x0D47, 0x0D48, // MALAYALAM
    ];

    /** Virama / halant code points. */
    private const VIRAMAS = [0x0ACD, 0x094D, 0x0A4D, 0x0B4D, 0x0BCD, 0x0C4D, 0x0CCD, 0x0D4D];

    /**
     * Vowel signs and combining marks that belong to the preceding base.
     *
     * The ranges start at the *first* dependent vowel sign of each block
     * (U+093E for Devanagari, U+0ABE for Gujarati) - starting any later
     * silently breaks the two most common signs in both scripts.
     */
    private const MARK_RANGES = [
        // Devanagari
        [0x0900, 0x0903], [0x093A, 0x093C], [0x093E, 0x094F], [0x0951, 0x0957], [0x0962, 0x0963],
        // Gujarati
        [0x0A81, 0x0A83], [0x0ABC, 0x0ABC], [0x0ABE, 0x0ACD], [0x0AE2, 0x0AE3],
        // Gurmukhi
        [0x0A01, 0x0A03], [0x0A3C, 0x0A3C], [0x0A3E, 0x0A4D],
        // Bengali / Oriya / Tamil / Telugu / Kannada / Malayalam
        [0x0981, 0x0983], [0x09BC, 0x09CD], [0x09D7, 0x09D7],
        [0x0B01, 0x0B03], [0x0B3C, 0x0B4D], [0x0B56, 0x0B57],
        [0x0B82, 0x0B82], [0x0BBE, 0x0BCD], [0x0BD7, 0x0BD7],
        [0x0C00, 0x0C04], [0x0C3E, 0x0C4D], [0x0C55, 0x0C56],
        [0x0C81, 0x0C83], [0x0CBC, 0x0CCD], [0x0CD5, 0x0CD6],
        [0x0D00, 0x0D03], [0x0D3B, 0x0D4D], [0x0D57, 0x0D57],
    ];

    /** True when the string contains any Indic code point. */
    public static function containsIndic(string $text): bool
    {
        return (bool) preg_match('/[\x{0900}-\x{0DFF}]/u', $text);
    }

    /**
     * Reorder a UTF-8 string into visual order.
     *
     * @return array<int,int> code points in the order they should be drawn
     */
    public static function toVisualOrder(string $text): array
    {
        $codes = self::codePoints($text);
        if ($codes === []) {
            return [];
        }
        if (!self::containsIndic($text)) {
            return $codes;
        }

        $out = [];
        $count = count($codes);
        $i = 0;

        while ($i < $count) {
            $code = $codes[$i];

            if (!self::isIndic($code)) {
                $out[] = $code;
                $i++;
                continue;
            }

            // Collect one orthographic cluster: base (with any virama chains)
            // followed by its marks.
            $clusterStart = $i;
            $cluster = [];
            $preBase = [];

            // Base consonant plus virama + consonant repetitions.
            $cluster[] = $codes[$i];
            $i++;
            while ($i + 1 < $count && self::isVirama($codes[$i]) && self::isIndic($codes[$i + 1])) {
                $cluster[] = $codes[$i];
                $cluster[] = $codes[$i + 1];
                $i += 2;
            }
            // Trailing virama with no following consonant (word-final halant).
            if ($i < $count && self::isVirama($codes[$i])) {
                $cluster[] = $codes[$i];
                $i++;
            }
            // Marks and vowel signs.
            while ($i < $count && self::isMark($codes[$i])) {
                if (in_array($codes[$i], self::PRE_BASE_MATRAS, true)) {
                    $preBase[] = $codes[$i];
                } else {
                    $cluster[] = $codes[$i];
                }
                $i++;
            }

            if ($clusterStart === $i) {
                // Defensive: never loop forever on unexpected input.
                $out[] = $code;
                $i++;
                continue;
            }

            foreach ($preBase as $matra) {
                $out[] = $matra;
            }
            foreach ($cluster as $member) {
                $out[] = $member;
            }
        }

        return $out;
    }

    /** @return array<int,int> */
    public static function codePoints(string $text): array
    {
        $codes = [];
        $length = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $ordinal = mb_ord($char, 'UTF-8');
            if ($ordinal !== false) {
                $codes[] = $ordinal;
            }
        }
        return $codes;
    }

    private static function isIndic(int $code): bool
    {
        return $code >= 0x0900 && $code <= 0x0DFF;
    }

    private static function isVirama(int $code): bool
    {
        return in_array($code, self::VIRAMAS, true);
    }

    private static function isMark(int $code): bool
    {
        foreach (self::MARK_RANGES as [$from, $to]) {
            if ($code >= $from && $code <= $to) {
                return true;
            }
        }
        return false;
    }

    /** True when a code point is a dependent vowel sign or combining mark. */
    public static function isCombining(int $code): bool
    {
        return self::isMark($code);
    }
}
