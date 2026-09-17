<?php

declare(strict_types=1);

namespace App\Core\Qr;

/**
 * QR Code generator - complete, dependency free implementation of
 * ISO/IEC 18004 byte mode for versions 1-40 and all four error correction
 * levels.
 *
 * Byte mode is used for every payload, which is the right choice for the URLs
 * this application encodes (lowercase slugs are outside the alphanumeric
 * charset anyway).
 *
 * Usage:
 *   $qr = QrCode::encode('https://example.com/i/8F3K9A', 'M');
 *   file_put_contents('qr.png', $qr->toPng(8));
 *   file_put_contents('qr.svg', $qr->toSvg(8));
 */
final class QrCode
{
    public const ECC_LOW      = 'L'; //  ~7% recovery
    public const ECC_MEDIUM   = 'M'; // ~15% recovery
    public const ECC_QUARTILE = 'Q'; // ~25% recovery
    public const ECC_HIGH     = 'H'; // ~30% recovery

    private const MIN_VERSION = 1;
    private const MAX_VERSION = 40;

    /** ECC codewords per block, indexed [level][version-1]. */
    private const ECC_CODEWORDS_PER_BLOCK = [
        'L' => [7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28,
                28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'M' => [10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26,
                26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        'Q' => [13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30,
                28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'H' => [17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28,
                30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    /** Number of error correction blocks, indexed [level][version-1]. */
    private const NUM_ECC_BLOCKS = [
        'L' => [1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8,
                8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        'M' => [1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16,
                17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        'Q' => [1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20,
                23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        'H' => [1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25,
                25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    /** Two-bit indicator written into the format information. */
    private const ECC_FORMAT_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];

    private int $size;
    /** @var array<int,array<int,bool>> true = dark */
    private array $modules = [];
    /** @var array<int,array<int,bool>> modules belonging to function patterns */
    private array $reserved = [];

    private function __construct(
        private readonly int $version,
        private readonly string $ecc,
        private int $mask = -1
    ) {
        $this->size = $version * 4 + 17;
        for ($y = 0; $y < $this->size; $y++) {
            $this->modules[$y] = array_fill(0, $this->size, false);
            $this->reserved[$y] = array_fill(0, $this->size, false);
        }
    }

    /**
     * Encode a string, choosing the smallest version that fits.
     *
     * @param string   $data    payload (any bytes; UTF-8 is fine)
     * @param string   $ecc     L|M|Q|H
     * @param int|null $version force a version, or null to auto-select
     * @param int      $mask    0-7, or -1 to auto-select the best mask
     */
    public static function encode(
        string $data,
        string $ecc = self::ECC_MEDIUM,
        ?int $version = null,
        int $mask = -1
    ): self {
        $ecc = strtoupper($ecc);
        if (!isset(self::ECC_CODEWORDS_PER_BLOCK[$ecc])) {
            throw new \InvalidArgumentException('Unknown error correction level: ' . $ecc);
        }
        if ($data === '') {
            throw new \InvalidArgumentException('Nothing to encode.');
        }

        $version ??= self::minimumVersion($data, $ecc);
        if ($version < self::MIN_VERSION || $version > self::MAX_VERSION) {
            throw new \InvalidArgumentException('QR version out of range.');
        }
        if (!self::fits($data, $version, $ecc)) {
            throw new \InvalidArgumentException('Data too long for the requested QR version.');
        }

        $qr = new self($version, $ecc, $mask);
        $codewords = $qr->buildCodewords($data);
        $qr->drawFunctionPatterns();
        $qr->drawCodewords($codewords);
        $qr->applyBestMask($mask);

        return $qr;
    }

    // ------------------------------------------------------------------
    //  Capacity
    // ------------------------------------------------------------------

    private static function rawDataModules(int $version): int
    {
        $raw = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $numAlign = intdiv($version, 7) + 2;
            // Alignment patterns are 5x5; the ones on the timing lines already
            // had 5 modules counted as timing, so give those back.
            $raw -= ($numAlign * $numAlign - 3) * 25;
            $raw += ($numAlign - 2) * 2 * 5;
            if ($version >= 7) {
                $raw -= 36; // version information blocks
            }
        }
        return $raw;
    }

    private static function totalCodewords(int $version): int
    {
        return intdiv(self::rawDataModules($version), 8);
    }

    /** Data (non-ECC) codewords available for a version/level pair. */
    public static function dataCapacity(int $version, string $ecc): int
    {
        $blocks = self::NUM_ECC_BLOCKS[$ecc][$version - 1];
        $eccPerBlock = self::ECC_CODEWORDS_PER_BLOCK[$ecc][$version - 1];
        return self::totalCodewords($version) - ($blocks * $eccPerBlock);
    }

    private static function characterCountBits(int $version): int
    {
        // Byte mode: 8 bits for versions 1-9, 16 bits for 10-40.
        return $version <= 9 ? 8 : 16;
    }

    private static function fits(string $data, int $version, string $ecc): bool
    {
        $needed = 4 + self::characterCountBits($version) + (strlen($data) * 8);
        return $needed <= self::dataCapacity($version, $ecc) * 8;
    }

    private static function minimumVersion(string $data, string $ecc): int
    {
        for ($version = self::MIN_VERSION; $version <= self::MAX_VERSION; $version++) {
            if (self::fits($data, $version, $ecc)) {
                return $version;
            }
        }
        throw new \InvalidArgumentException('Payload is too large for a QR code (max ~2953 bytes).');
    }

    // ------------------------------------------------------------------
    //  Data encoding
    // ------------------------------------------------------------------

    /** @return array<int,int> final interleaved codeword sequence */
    private function buildCodewords(string $data): array
    {
        $bits = new BitBuffer();
        $bits->append(0b0100, 4);                                   // byte mode
        $bits->append(strlen($data), self::characterCountBits($this->version));
        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $bits->append(ord($data[$i]), 8);
        }

        $capacityBits = self::dataCapacity($this->version, $this->ecc) * 8;

        // Terminator (up to four zero bits) then pad to a byte boundary.
        $bits->append(0, min(4, $capacityBits - $bits->length()));
        $bits->append(0, (8 - ($bits->length() % 8)) % 8);

        // Alternating pad bytes, as required by the specification.
        $padBytes = [0xEC, 0x11];
        $i = 0;
        while ($bits->length() < $capacityBits) {
            $bits->append($padBytes[$i % 2], 8);
            $i++;
        }

        return $this->addErrorCorrection($bits->toBytes());
    }

    /**
     * Split the data codewords into blocks, compute Reed-Solomon parity for
     * each, then interleave as the specification requires.
     *
     * @param array<int,int> $data
     * @return array<int,int>
     */
    private function addErrorCorrection(array $data): array
    {
        $version = $this->version;
        $numBlocks = self::NUM_ECC_BLOCKS[$this->ecc][$version - 1];
        $eccLen = self::ECC_CODEWORDS_PER_BLOCK[$this->ecc][$version - 1];
        $totalCodewords = self::totalCodewords($version);

        $shortBlockLen = intdiv($totalCodewords, $numBlocks) - $eccLen;
        $numLongBlocks = $totalCodewords % $numBlocks;

        $generator = ReedSolomon::generator($eccLen);

        $dataBlocks = [];
        $eccBlocks = [];
        $offset = 0;
        for ($block = 0; $block < $numBlocks; $block++) {
            $length = $shortBlockLen + ($block >= $numBlocks - $numLongBlocks ? 1 : 0);
            $chunk = array_slice($data, $offset, $length);
            $offset += $length;
            $dataBlocks[] = $chunk;
            $eccBlocks[] = ReedSolomon::remainder($chunk, $generator);
        }

        $result = [];
        $maxDataLen = $shortBlockLen + ($numLongBlocks > 0 ? 1 : 0);
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($dataBlocks as $block) {
                if ($i < count($block)) {
                    $result[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $eccLen; $i++) {
            foreach ($eccBlocks as $block) {
                $result[] = $block[$i];
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------
    //  Matrix construction
    // ------------------------------------------------------------------

    private function drawFunctionPatterns(): void
    {
        // Timing patterns.
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }

        // Three finder patterns with their separators.
        $this->drawFinder(3, 3);
        $this->drawFinder($this->size - 4, 3);
        $this->drawFinder(3, $this->size - 4);

        // Alignment patterns.
        $positions = $this->alignmentPositions();
        $count = count($positions);
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                // Skip the three corners occupied by finder patterns.
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $count - 1) || ($i === $count - 1 && $j === 0)) {
                    continue;
                }
                $this->drawAlignment($positions[$i], $positions[$j]);
            }
        }

        // Reserve the format and version areas; real values come later.
        $this->drawFormatBits(0);
        $this->drawVersionBits();
    }

    private function drawFinder(int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $distance = max(abs($dx), abs($dy)); // Chebyshev ring index
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) {
                    $this->setFunction($x, $y, $distance !== 2 && $distance !== 4);
                }
            }
        }
    }

    private function drawAlignment(int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunction($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    /** @return array<int,int> */
    private function alignmentPositions(): array
    {
        if ($this->version === 1) {
            return [];
        }
        $numAlign = intdiv($this->version, 7) + 2;
        $step = $this->version === 32
            ? 26
            : intdiv($this->version * 4 + $numAlign * 2 + 1, $numAlign * 2 - 2) * 2;

        $result = array_fill(0, $numAlign, 0);
        $result[0] = 6;
        for ($i = $numAlign - 1, $pos = $this->size - 7; $i >= 1; $i--, $pos -= $step) {
            $result[$i] = $pos;
        }
        return $result;
    }

    /** Format information: BCH(15,5) over the level + mask, XOR 0x5412. */
    private function drawFormatBits(int $mask): void
    {
        $data = (self::ECC_FORMAT_BITS[$this->ecc] << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        // Top-left copy.
        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, $this->bit($bits, $i));
        }
        $this->setFunction(8, 7, $this->bit($bits, 6));
        $this->setFunction(8, 8, $this->bit($bits, 7));
        $this->setFunction(7, 8, $this->bit($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, $this->bit($bits, $i));
        }

        // Second copy, split across the other two corners.
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($this->size - 1 - $i, 8, $this->bit($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $this->size - 15 + $i, $this->bit($bits, $i));
        }
        $this->setFunction(8, $this->size - 8, true); // the permanent dark module
    }

    /** Version information: BCH(18,6), only for versions 7 and above. */
    private function drawVersionBits(): void
    {
        if ($this->version < 7) {
            return;
        }
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem;

        for ($i = 0; $i < 18; $i++) {
            $bit = $this->bit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunction($a, $b, $bit);
            $this->setFunction($b, $a, $bit);
        }
    }

    private function bit(int $value, int $index): bool
    {
        return (($value >> $index) & 1) !== 0;
    }

    /**
     * Zigzag placement of the codeword bits: two-module-wide columns from the
     * right, alternating direction, skipping the vertical timing pattern.
     *
     * @param array<int,int> $codewords
     */
    private function drawCodewords(array $codewords): void
    {
        $bitIndex = 0;
        $totalBits = count($codewords) * 8;

        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5; // skip the timing column
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = ((($right + 1) & 2) === 0);
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if ($this->reserved[$y][$x]) {
                        continue;
                    }
                    if ($bitIndex < $totalBits) {
                        $byte = $codewords[$bitIndex >> 3];
                        $this->modules[$y][$x] = (($byte >> (7 - ($bitIndex & 7))) & 1) !== 0;
                        $bitIndex++;
                    }
                    // Remainder bits stay light, which is what the spec wants.
                }
            }
        }
    }

    private function setFunction(int $x, int $y, bool $dark): void
    {
        if ($x < 0 || $y < 0 || $x >= $this->size || $y >= $this->size) {
            return;
        }
        $this->modules[$y][$x] = $dark;
        $this->reserved[$y][$x] = true;
    }

    // ------------------------------------------------------------------
    //  Masking
    // ------------------------------------------------------------------

    private function applyBestMask(int $requested): void
    {
        if ($requested >= 0 && $requested <= 7) {
            $this->applyMask($requested);
            $this->drawFormatBits($requested);
            $this->mask = $requested;
            return;
        }

        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $penalty = $this->penalty();
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
            }
            $this->applyMask($mask); // XOR again to undo
        }

        $this->applyMask($bestMask);
        $this->drawFormatBits($bestMask);
        $this->mask = $bestMask;
    }

    /** XOR the data modules with the chosen mask pattern. */
    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->reserved[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    7 => ((($x + $y) % 2) + (($x * $y) % 3)) % 2 === 0,
                    default => false,
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** The four penalty rules from the specification. */
    private function penalty(): int
    {
        $penalty = 0;
        $size = $this->size;

        // Rule 1: runs of five or more same-coloured modules in a line.
        for ($y = 0; $y < $size; $y++) {
            $penalty += $this->linePenalty($this->modules[$y]);
        }
        for ($x = 0; $x < $size; $x++) {
            $column = [];
            for ($y = 0; $y < $size; $y++) {
                $column[] = $this->modules[$y][$x];
            }
            $penalty += $this->linePenalty($column);
        }

        // Rule 2: 2x2 blocks of the same colour.
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $value = $this->modules[$y][$x];
                if ($value === $this->modules[$y][$x + 1]
                    && $value === $this->modules[$y + 1][$x]
                    && $value === $this->modules[$y + 1][$x + 1]) {
                    $penalty += 3;
                }
            }
        }

        // Rule 3: finder-like 1:1:3:1:1 patterns with four light modules.
        $pattern = [true, false, true, true, true, false, true];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x <= $size - 7; $x++) {
                if ($this->matchesPattern($this->modules[$y], $x, $pattern)
                    && ($this->lightRun($this->modules[$y], $x - 4, 4) || $this->lightRun($this->modules[$y], $x + 7, 4))) {
                    $penalty += 40;
                }
            }
        }
        for ($x = 0; $x < $size; $x++) {
            $column = [];
            for ($y = 0; $y < $size; $y++) {
                $column[] = $this->modules[$y][$x];
            }
            for ($y = 0; $y <= $size - 7; $y++) {
                if ($this->matchesPattern($column, $y, $pattern)
                    && ($this->lightRun($column, $y - 4, 4) || $this->lightRun($column, $y + 7, 4))) {
                    $penalty += 40;
                }
            }
        }

        // Rule 4: deviation from a 50/50 dark ratio.
        $dark = 0;
        foreach ($this->modules as $row) {
            foreach ($row as $value) {
                if ($value) {
                    $dark++;
                }
            }
        }
        $total = $size * $size;
        $ratioDeviation = (int) (abs($dark * 20 - $total * 10) / $total);
        $penalty += $ratioDeviation * 10;

        return $penalty;
    }

    /** @param array<int,bool> $line */
    private function linePenalty(array $line): int
    {
        $penalty = 0;
        $runLength = 1;
        $count = count($line);
        for ($i = 1; $i < $count; $i++) {
            if ($line[$i] === $line[$i - 1]) {
                $runLength++;
                if ($runLength === 5) {
                    $penalty += 3;
                } elseif ($runLength > 5) {
                    $penalty++;
                }
            } else {
                $runLength = 1;
            }
        }
        return $penalty;
    }

    /** @param array<int,bool> $line */
    private function matchesPattern(array $line, int $offset, array $pattern): bool
    {
        foreach ($pattern as $i => $expected) {
            if (($line[$offset + $i] ?? null) !== $expected) {
                return false;
            }
        }
        return true;
    }

    /** @param array<int,bool> $line */
    private function lightRun(array $line, int $start, int $length): bool
    {
        for ($i = $start; $i < $start + $length; $i++) {
            // Outside the symbol counts as light (quiet zone).
            if (($line[$i] ?? false) === true) {
                return false;
            }
        }
        return true;
    }

    // ------------------------------------------------------------------
    //  Accessors and renderers
    // ------------------------------------------------------------------

    public function size(): int
    {
        return $this->size;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function eccLevel(): string
    {
        return $this->ecc;
    }

    public function maskPattern(): int
    {
        return $this->mask;
    }

    public function isDark(int $x, int $y): bool
    {
        return ($this->modules[$y][$x] ?? false) === true;
    }

    /** @return array<int,array<int,bool>> */
    public function matrix(): array
    {
        return $this->modules;
    }

    /**
     * Render as a PNG.
     *
     * @param int    $scale  pixels per module
     * @param int    $margin quiet zone in modules (4 is the standard minimum)
     * @param string $dark   hex colour, e.g. #1A1A1A
     * @param string $light  hex colour or 'transparent'
     */
    public function toPng(int $scale = 8, int $margin = 4, string $dark = '#000000', string $light = '#FFFFFF'): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('The GD extension is required to render a PNG QR code.');
        }
        $scale = max(1, min(40, $scale));
        $margin = max(0, min(16, $margin));
        $pixels = ($this->size + $margin * 2) * $scale;

        $image = imagecreatetruecolor($pixels, $pixels);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        [$dr, $dg, $db] = self::hexToRgb($dark);
        $darkColor = imagecolorallocate($image, $dr, $dg, $db);

        if (strtolower($light) === 'transparent') {
            $lightColor = imagecolorallocatealpha($image, 255, 255, 255, 127);
        } else {
            [$lr, $lg, $lb] = self::hexToRgb($light);
            $lightColor = imagecolorallocate($image, $lr, $lg, $lb);
        }

        imagefilledrectangle($image, 0, 0, $pixels - 1, $pixels - 1, $lightColor);

        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if (!$this->modules[$y][$x]) {
                    continue;
                }
                $px = ($x + $margin) * $scale;
                $py = ($y + $margin) * $scale;
                imagefilledrectangle($image, $px, $py, $px + $scale - 1, $py + $scale - 1, $darkColor);
            }
        }

        ob_start();
        imagepng($image, null, 9);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    /** Render as an SVG - crisp at any print size, tiny file. */
    public function toSvg(int $scale = 8, int $margin = 4, string $dark = '#000000', string $light = '#FFFFFF'): string
    {
        $scale = max(1, min(80, $scale));
        $margin = max(0, min(16, $margin));
        $dimension = $this->size + $margin * 2;
        $pixels = $dimension * $scale;

        $dark = self::safeColor($dark);
        $paths = [];
        for ($y = 0; $y < $this->size; $y++) {
            $x = 0;
            while ($x < $this->size) {
                if (!$this->modules[$y][$x]) {
                    $x++;
                    continue;
                }
                // Merge horizontal runs into one rect: far fewer nodes.
                $run = 1;
                while ($x + $run < $this->size && $this->modules[$y][$x + $run]) {
                    $run++;
                }
                $paths[] = sprintf(
                    'M%d %dh%dv1h-%dz',
                    $x + $margin,
                    $y + $margin,
                    $run,
                    $run
                );
                $x += $run;
            }
        }

        $background = strtolower($light) === 'transparent'
            ? ''
            : '<rect width="' . $dimension . '" height="' . $dimension . '" fill="' . self::safeColor($light) . '"/>';

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $pixels . '" height="' . $pixels . '" '
            . 'viewBox="0 0 ' . $dimension . ' ' . $dimension . '" shape-rendering="crispEdges" role="img" '
            . 'aria-label="QR code">'
            . $background
            . '<path fill="' . $dark . '" d="' . implode('', $paths) . '"/>'
            . '</svg>';
    }

    /** Monospace text rendering, handy for CLI verification. */
    public function toText(string $darkChar = '██', string $lightChar = '  '): string
    {
        $out = '';
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                $out .= $this->modules[$y][$x] ? $darkChar : $lightChar;
            }
            $out .= "\n";
        }
        return $out;
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [0, 0, 0];
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function safeColor(string $color): string
    {
        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($color)) === 1
            ? trim($color)
            : '#000000';
    }
}
