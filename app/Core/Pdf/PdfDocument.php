<?php

declare(strict_types=1);

namespace App\Core\Pdf;

/**
 * PDF writer - no external library required.
 *
 * Produces PDF 1.4 documents with:
 *   - multiple pages, any page size (A4 by default)
 *   - embedded, subsetted TrueType fonts as CIDFontType2 with Identity-H
 *     encoding, so Gujarati, Hindi and Latin all render from real Unicode
 *   - a ToUnicode CMap, so the text is selectable and searchable
 *   - JPEG (pass-through) and PNG/GIF/WebP (re-encoded) images
 *   - vector primitives, transparency and linear gradients for ornaments
 *
 * The coordinate system exposed here has its origin at the TOP-LEFT of the
 * page and is measured in points, which matches how layouts are written.
 */
final class PdfDocument
{
    /** Page sizes in points (1pt = 1/72 inch). */
    public const SIZES = [
        'A4'        => [595.28, 841.89],
        'A5'        => [419.53, 595.28],
        'LETTER'    => [612.00, 792.00],
        'MOBILE'    => [396.00, 704.00], // 5.5 x 9.78 in - fits a phone screen
        'SQUARE'    => [595.28, 595.28],
        'KANKOTRI'  => [510.24, 737.01], // 180 x 260 mm - classic card size
    ];

    private const MM_TO_PT = 2.83465;

    /** @var array<int,string> raw PDF objects, 1-indexed */
    private array $objects = [];

    /** @var array<int,array{width:float,height:float,content:string,number:int}> */
    private array $pages = [];
    private int $currentPage = -1;

    /** @var array<string,array{font:TrueTypeFont,alias:string,used:array<int,int>,object:int|null,path:string}> */
    private array $fonts = [];
    private ?string $currentFont = null;
    private float $currentSize = 12.0;

    /** @var array<string,array{object:int,width:int,height:int}> */
    private array $images = [];

    /** @var array<string,int> alpha value => ExtGState object number */
    private array $alphaStates = [];

    /** @var array<int,array{object:int,name:string}> */
    private array $shadings = [];

    private array $info = [
        'Title'    => '',
        'Author'   => '',
        'Subject'  => '',
        'Keywords' => '',
        'Creator'  => 'Shubh Kankotri',
        'Producer' => 'Shubh Kankotri PDF engine',
    ];

    private float $pageWidth;
    private float $pageHeight;
    private bool $compress = true;

    public function __construct(string $size = 'A4', string $orientation = 'portrait')
    {
        [$this->pageWidth, $this->pageHeight] = self::resolveSize($size, $orientation);
    }

    /** @return array{0:float,1:float} */
    public static function resolveSize(string $size, string $orientation = 'portrait'): array
    {
        $key = strtoupper($size);
        $dimensions = self::SIZES[$key] ?? self::SIZES['A4'];
        if (strtolower($orientation) === 'landscape') {
            $dimensions = [$dimensions[1], $dimensions[0]];
        }
        return $dimensions;
    }

    public static function mm(float $millimetres): float
    {
        return $millimetres * self::MM_TO_PT;
    }

    public function setCompression(bool $enabled): void
    {
        $this->compress = $enabled && function_exists('gzcompress');
    }

    public function setInfo(string $key, string $value): void
    {
        if (array_key_exists($key, $this->info)) {
            $this->info[$key] = $value;
        }
    }

    public function width(): float
    {
        return $this->pageWidth;
    }

    public function height(): float
    {
        return $this->pageHeight;
    }

    // ------------------------------------------------------------------
    //  Pages
    // ------------------------------------------------------------------

    public function addPage(?float $width = null, ?float $height = null): int
    {
        $this->pages[] = [
            'width'   => $width ?? $this->pageWidth,
            'height'  => $height ?? $this->pageHeight,
            'content' => '',
            'number'  => count($this->pages) + 1,
        ];
        $this->currentPage = count($this->pages) - 1;
        $this->currentFont = null;
        return $this->currentPage;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function currentPageWidth(): float
    {
        return $this->pages[$this->currentPage]['width'] ?? $this->pageWidth;
    }

    public function currentPageHeight(): float
    {
        return $this->pages[$this->currentPage]['height'] ?? $this->pageHeight;
    }

    private function write(string $operators): void
    {
        if ($this->currentPage < 0) {
            $this->addPage();
        }
        $this->pages[$this->currentPage]['content'] .= $operators . "\n";
    }

    /** Convert a top-left y coordinate into PDF's bottom-left space. */
    private function y(float $y): float
    {
        return $this->currentPageHeight() - $y;
    }

    // ------------------------------------------------------------------
    //  Fonts
    // ------------------------------------------------------------------

    /**
     * Register a TrueType font under a short alias used by setFont().
     *
     * Registering the same alias twice is a no-op, so layouts can declare
     * their fonts freely.
     */
    public function registerFont(string $alias, string $path): bool
    {
        if (isset($this->fonts[$alias])) {
            return true;
        }
        try {
            $font = TrueTypeFont::fromFile($path);
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('PDF font could not be loaded: ' . $e->getMessage(), [
                'font' => basename($path),
            ]);
            return false;
        }
        $this->fonts[$alias] = [
            'font'   => $font,
            'alias'  => $alias,
            'used'   => [],
            'object' => null,
            'path'   => $path,
        ];
        return true;
    }

    public function hasFont(string $alias): bool
    {
        return isset($this->fonts[$alias]);
    }

    /** @return array<int,string> */
    public function registeredFonts(): array
    {
        return array_keys($this->fonts);
    }

    public function setFont(string $alias, float $size): void
    {
        if (!isset($this->fonts[$alias])) {
            $alias = (string) (array_key_first($this->fonts) ?? '');
            if ($alias === '') {
                throw new \RuntimeException('No font has been registered for this PDF.');
            }
        }
        $this->currentFont = $alias;
        $this->currentSize = max(1.0, $size);
    }

    public function fontSize(): float
    {
        return $this->currentSize;
    }

    /**
     * Width of a string in points at the current (or given) size.
     */
    public function textWidth(string $text, ?float $size = null, ?string $alias = null): float
    {
        $alias ??= $this->currentFont;
        if ($alias === null || !isset($this->fonts[$alias])) {
            return 0.0;
        }
        $size ??= $this->currentSize;
        $font = $this->fonts[$alias]['font'];

        $total = 0;
        foreach (IndicText::toVisualOrder($text) as $code) {
            $gid = $font->glyphFor($code);
            if ($gid === 0 && $code !== 32) {
                // Unmapped: approximate with the space advance so layout
                // still behaves sensibly.
                $gid = $font->glyphFor(32);
            }
            // Zero-advance combining marks draw over their base and so add
            // nothing to the measured width - the same rule the renderer uses.
            $total += $font->widthOfGlyph($gid);
        }
        return $total * $size / 1000;
    }

    /** Does this font have a glyph for every character in the string? */
    public function fontCovers(string $alias, string $text): bool
    {
        if (!isset($this->fonts[$alias])) {
            return false;
        }
        $font = $this->fonts[$alias]['font'];
        foreach (IndicText::codePoints($text) as $code) {
            if ($code === 32 || $code === 10 || $code === 13 || $code === 9) {
                continue;
            }
            if (!$font->hasGlyph($code)) {
                return false;
            }
        }
        return true;
    }

    // ------------------------------------------------------------------
    //  Text
    // ------------------------------------------------------------------

    /**
     * Draw a single line of text.
     *
     * @param string $align left|center|right relative to $x (center/right use
     *                      $x as the anchor point)
     */
    public function text(
        float $x,
        float $y,
        string $text,
        string $color = '#000000',
        string $align = 'left',
        ?float $letterSpacing = null
    ): void {
        if ($text === '' || $this->currentFont === null) {
            return;
        }
        [$show, $width] = $this->buildGlyphRun($this->currentFont, $text);
        if ($show === '') {
            return;
        }

        $startX = match ($align) {
            'center' => $x - ($width / 2),
            'right'  => $x - $width,
            default  => $x,
        };

        $operators = 'BT ' . $this->colorOperator($color, true)
            . ' /' . $this->fontResourceName($this->currentFont) . ' ' . $this->number($this->currentSize) . ' Tf';
        if ($letterSpacing !== null) {
            $operators .= ' ' . $this->number($letterSpacing) . ' Tc';
        }
        $operators .= ' 1 0 0 1 ' . $this->number($startX) . ' ' . $this->number($this->y($y)) . ' Tm'
            . ' ' . $show . ' ET';

        $this->write($operators);
    }

    /**
     * Turn a string into a PDF show-text operator plus its measured width.
     *
     * Combining marks (Indic vowel signs, anusvara, nukta, virama) carry a
     * zero advance in the font and are meant to be anchored to their base by
     * GPOS. There is no GPOS engine here, so instead of letting a zero-width
     * mark land *after* the base glyph - which is what makes naive Unicode
     * PDF output unreadable in Gujarati and Hindi - the pen is walked back
     * over the base with a TJ adjustment, the mark is drawn, and the pen is
     * restored. See docs/PDF.md for what this does and does not cover.
     *
     * @return array{0:string,1:float} operator, width in points
     */
    private function buildGlyphRun(string $alias, string $text): array
    {
        $entry = &$this->fonts[$alias];
        $font = $entry['font'];

        /** @var array<int,string|int> $items TJ array: hex strings and adjustments */
        $items = [];
        $buffer = '';
        $widthUnits = 0;
        $baseAdvance = 0;
        $baseInkCentre = 0;
        $markShift = 0;

        $flush = static function () use (&$items, &$buffer): void {
            if ($buffer !== '') {
                $items[] = '<' . $buffer . '>';
                $buffer = '';
            }
        };

        foreach (IndicText::toVisualOrder($text) as $code) {
            $gid = $font->glyphFor($code);
            if ($gid === 0 && $code !== 32) {
                // Unmapped character: fall back to a space so the line keeps
                // its shape rather than collapsing.
                $gid = $font->glyphFor(32);
                $code = 32;
            }
            $entry['used'][$gid] = $code;
            $advance = $font->widthOfGlyph($gid);

            if ($advance === 0 && $baseAdvance > 0 && IndicText::isCombining($code)) {
                // Centre the mark's ink over the base glyph's ink.
                // Positive TJ numbers move the pen left.
                $shift = (int) round($baseAdvance + $font->glyphInkCentre($gid) - $baseInkCentre);
                $flush();
                if ($markShift !== 0) {
                    $items[] = -$markShift;
                    $markShift = 0;
                }
                $items[] = $shift;
                $buffer .= sprintf('%04X', $gid);
                $flush();
                $items[] = -$shift;
                continue;
            }

            if ($markShift !== 0) {
                $flush();
                $items[] = -$markShift;        // step back to where we were
                $markShift = 0;
            }

            $buffer .= sprintf('%04X', $gid);
            $widthUnits += $advance;
            if ($advance > 0) {
                $baseAdvance = $advance;
                $baseInkCentre = $font->glyphInkCentre($gid);
            }
        }

        if ($markShift !== 0) {
            $flush();
            $items[] = -$markShift;
        }
        $flush();
        unset($entry);

        if ($items === []) {
            return ['', 0.0];
        }

        // A run with no adjustments is cheaper and smaller as a plain Tj.
        if (count($items) === 1 && is_string($items[0])) {
            return [$items[0] . ' Tj', $widthUnits * $this->currentSize / 1000];
        }

        $parts = [];
        foreach ($items as $item) {
            $parts[] = is_string($item) ? $item : (string) $item;
        }

        return ['[' . implode(' ', $parts) . '] TJ', $widthUnits * $this->currentSize / 1000];
    }

    /**
     * Break text into lines that fit a width.
     *
     * @return array<int,string>
     */
    public function wrapText(string $text, float $maxWidth, ?float $size = null, ?string $alias = null): array
    {
        $size ??= $this->currentSize;
        $alias ??= $this->currentFont;
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [$text] as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }
            $words = preg_split('/\s+/u', $paragraph) ?: [$paragraph];
            $line = '';
            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if ($this->textWidth($candidate, $size, $alias) <= $maxWidth || $line === '') {
                    $line = $candidate;
                    // A single word longer than the column has to be split.
                    while ($this->textWidth($line, $size, $alias) > $maxWidth && mb_strlen($line) > 1) {
                        $cut = mb_strlen($line);
                        while ($cut > 1 && $this->textWidth(mb_substr($line, 0, $cut), $size, $alias) > $maxWidth) {
                            $cut--;
                        }
                        $lines[] = mb_substr($line, 0, $cut);
                        $line = mb_substr($line, $cut);
                    }
                    continue;
                }
                $lines[] = $line;
                $line = $word;
            }
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Draw wrapped text and return the y coordinate just below it.
     */
    public function paragraph(
        float $x,
        float $y,
        float $maxWidth,
        string $text,
        string $color = '#000000',
        string $align = 'left',
        ?float $lineHeight = null
    ): float {
        $lineHeight ??= $this->currentSize * 1.45;
        $lines = $this->wrapText($text, $maxWidth);
        $anchorX = match ($align) {
            'center' => $x + ($maxWidth / 2),
            'right'  => $x + $maxWidth,
            default  => $x,
        };
        foreach ($lines as $line) {
            if ($line !== '') {
                $this->text($anchorX, $y, $line, $color, $align);
            }
            $y += $lineHeight;
        }
        return $y;
    }

    // ------------------------------------------------------------------
    //  Vector graphics
    // ------------------------------------------------------------------

    public function rect(
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $fill = '#000000',
        ?string $stroke = null,
        float $lineWidth = 1.0
    ): void {
        $operators = '';
        if ($fill !== null) {
            $operators .= $this->colorOperator($fill, false) . ' ';
        }
        if ($stroke !== null) {
            $operators .= $this->colorOperator($stroke, true, true) . ' ' . $this->number($lineWidth) . ' w ';
        }
        $operators .= $this->number($x) . ' ' . $this->number($this->y($y) - $height) . ' '
            . $this->number($width) . ' ' . $this->number($height) . ' re ';
        $operators .= $this->paintOperator($fill !== null, $stroke !== null);
        $this->write($operators);
    }

    public function roundedRect(
        float $x,
        float $y,
        float $width,
        float $height,
        float $radius,
        ?string $fill = '#000000',
        ?string $stroke = null,
        float $lineWidth = 1.0
    ): void {
        $radius = max(0.0, min($radius, min($width, $height) / 2));
        $bottom = $this->y($y) - $height;
        $top = $this->y($y);
        $left = $x;
        $right = $x + $width;
        $k = $radius * 0.5523; // circle approximation constant

        $operators = '';
        if ($fill !== null) {
            $operators .= $this->colorOperator($fill, false) . ' ';
        }
        if ($stroke !== null) {
            $operators .= $this->colorOperator($stroke, true, true) . ' ' . $this->number($lineWidth) . ' w ';
        }

        $operators .= $this->number($left + $radius) . ' ' . $this->number($top) . ' m ';
        $operators .= $this->number($right - $radius) . ' ' . $this->number($top) . ' l ';
        $operators .= $this->curve($right - $radius + $k, $top, $right, $top - $radius + $k, $right, $top - $radius);
        $operators .= $this->number($right) . ' ' . $this->number($bottom + $radius) . ' l ';
        $operators .= $this->curve($right, $bottom + $radius - $k, $right - $radius + $k, $bottom, $right - $radius, $bottom);
        $operators .= $this->number($left + $radius) . ' ' . $this->number($bottom) . ' l ';
        $operators .= $this->curve($left + $radius - $k, $bottom, $left, $bottom + $radius - $k, $left, $bottom + $radius);
        $operators .= $this->number($left) . ' ' . $this->number($top - $radius) . ' l ';
        $operators .= $this->curve($left, $top - $radius + $k, $left + $radius - $k, $top, $left + $radius, $top);
        $operators .= 'h ' . $this->paintOperator($fill !== null, $stroke !== null);

        $this->write($operators);
    }

    public function line(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        string $color = '#000000',
        float $lineWidth = 1.0,
        ?array $dash = null
    ): void {
        $operators = $this->colorOperator($color, true, true) . ' ' . $this->number($lineWidth) . ' w ';
        if ($dash !== null && $dash !== []) {
            $operators .= '[' . implode(' ', array_map([$this, 'number'], $dash)) . '] 0 d ';
        }
        $operators .= $this->number($x1) . ' ' . $this->number($this->y($y1)) . ' m '
            . $this->number($x2) . ' ' . $this->number($this->y($y2)) . ' l S';
        if ($dash !== null) {
            $operators .= "\n[] 0 d";
        }
        $this->write($operators);
    }

    public function circle(
        float $cx,
        float $cy,
        float $radius,
        ?string $fill = '#000000',
        ?string $stroke = null,
        float $lineWidth = 1.0
    ): void {
        $k = $radius * 0.5523;
        $y = $this->y($cy);

        $operators = '';
        if ($fill !== null) {
            $operators .= $this->colorOperator($fill, false) . ' ';
        }
        if ($stroke !== null) {
            $operators .= $this->colorOperator($stroke, true, true) . ' ' . $this->number($lineWidth) . ' w ';
        }
        $operators .= $this->number($cx + $radius) . ' ' . $this->number($y) . ' m ';
        $operators .= $this->curve($cx + $radius, $y + $k, $cx + $k, $y + $radius, $cx, $y + $radius);
        $operators .= $this->curve($cx - $k, $y + $radius, $cx - $radius, $y + $k, $cx - $radius, $y);
        $operators .= $this->curve($cx - $radius, $y - $k, $cx - $k, $y - $radius, $cx, $y - $radius);
        $operators .= $this->curve($cx + $k, $y - $radius, $cx + $radius, $y - $k, $cx + $radius, $y);
        $operators .= 'h ' . $this->paintOperator($fill !== null, $stroke !== null);

        $this->write($operators);
    }

    /** Filled polygon from a list of [x, y] points (top-left coordinates). */
    public function polygon(array $points, ?string $fill = '#000000', ?string $stroke = null, float $lineWidth = 1.0): void
    {
        if (count($points) < 2) {
            return;
        }
        $operators = '';
        if ($fill !== null) {
            $operators .= $this->colorOperator($fill, false) . ' ';
        }
        if ($stroke !== null) {
            $operators .= $this->colorOperator($stroke, true, true) . ' ' . $this->number($lineWidth) . ' w ';
        }
        foreach ($points as $index => [$px, $py]) {
            $operators .= $this->number((float) $px) . ' ' . $this->number($this->y((float) $py))
                . ($index === 0 ? ' m ' : ' l ');
        }
        $operators .= 'h ' . $this->paintOperator($fill !== null, $stroke !== null);
        $this->write($operators);
    }

    private function curve(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): string
    {
        return $this->number($x1) . ' ' . $this->number($y1) . ' '
            . $this->number($x2) . ' ' . $this->number($y2) . ' '
            . $this->number($x3) . ' ' . $this->number($y3) . ' c ';
    }

    private function paintOperator(bool $fill, bool $stroke): string
    {
        return match (true) {
            $fill && $stroke => 'B',
            $stroke          => 'S',
            default          => 'f',
        };
    }

    // ------------------------------------------------------------------
    //  Transparency, clipping and gradients
    // ------------------------------------------------------------------

    public function save(): void
    {
        $this->write('q');
    }

    public function restore(): void
    {
        $this->write('Q');
    }

    /** Set constant alpha for subsequent fills (0.0 - 1.0). */
    public function setAlpha(float $alpha): void
    {
        $alpha = max(0.0, min(1.0, $alpha));
        $key = number_format($alpha, 2, '.', '');
        if (!isset($this->alphaStates[$key])) {
            $this->alphaStates[$key] = $this->addObject(
                '<< /Type /ExtGState /ca ' . $key . ' /CA ' . $key . ' >>'
            );
        }
        $this->write('/GS' . str_replace('.', '', $key) . ' gs');
    }

    /** Clip subsequent drawing to a rectangle. Pair with save()/restore(). */
    public function clipRect(float $x, float $y, float $width, float $height): void
    {
        $this->write(
            $this->number($x) . ' ' . $this->number($this->y($y) - $height) . ' '
            . $this->number($width) . ' ' . $this->number($height) . ' re W n'
        );
    }

    /** Clip to a rounded rectangle - used for photo frames. */
    public function clipRoundedRect(float $x, float $y, float $width, float $height, float $radius): void
    {
        $radius = max(0.0, min($radius, min($width, $height) / 2));
        $bottom = $this->y($y) - $height;
        $top = $this->y($y);
        $left = $x;
        $right = $x + $width;
        $k = $radius * 0.5523;

        $operators = $this->number($left + $radius) . ' ' . $this->number($top) . ' m '
            . $this->number($right - $radius) . ' ' . $this->number($top) . ' l '
            . $this->curve($right - $radius + $k, $top, $right, $top - $radius + $k, $right, $top - $radius)
            . $this->number($right) . ' ' . $this->number($bottom + $radius) . ' l '
            . $this->curve($right, $bottom + $radius - $k, $right - $radius + $k, $bottom, $right - $radius, $bottom)
            . $this->number($left + $radius) . ' ' . $this->number($bottom) . ' l '
            . $this->curve($left + $radius - $k, $bottom, $left, $bottom + $radius - $k, $left, $bottom + $radius)
            . $this->number($left) . ' ' . $this->number($top - $radius) . ' l '
            . $this->curve($left, $top - $radius + $k, $left + $radius - $k, $top, $left + $radius, $top)
            . 'h W n';

        $this->write($operators);
    }

    /**
     * Vertical linear gradient behind a rectangle - the background wash used
     * by most invitation layouts.
     */
    public function gradientRect(
        float $x,
        float $y,
        float $width,
        float $height,
        string $from,
        string $to,
        bool $horizontal = false
    ): void {
        [$r1, $g1, $b1] = self::hexToRgbFloat($from);
        [$r2, $g2, $b2] = self::hexToRgbFloat($to);

        $function = $this->addObject(
            '<< /FunctionType 2 /Domain [0 1] '
            . '/C0 [' . $this->number($r1) . ' ' . $this->number($g1) . ' ' . $this->number($b1) . '] '
            . '/C1 [' . $this->number($r2) . ' ' . $this->number($g2) . ' ' . $this->number($b2) . '] '
            . '/N 1 >>'
        );

        $top = $this->y($y);
        $bottom = $top - $height;
        $coords = $horizontal
            ? [$x, $bottom, $x + $width, $bottom]
            : [$x, $top, $x, $bottom];

        $shading = $this->addObject(
            '<< /ShadingType 2 /ColorSpace /DeviceRGB '
            . '/Coords [' . implode(' ', array_map([$this, 'number'], $coords)) . '] '
            . '/Function ' . $function . ' 0 R /Extend [true true] >>'
        );

        $name = 'Sh' . (count($this->shadings) + 1);
        $this->shadings[] = ['object' => $shading, 'name' => $name];

        $this->write(
            'q ' . $this->number($x) . ' ' . $this->number($bottom) . ' '
            . $this->number($width) . ' ' . $this->number($height) . ' re W n '
            . '/' . $name . ' sh Q'
        );
    }

    // ------------------------------------------------------------------
    //  Images
    // ------------------------------------------------------------------

    /**
     * Place an image file. JPEG data is embedded as-is (no re-encoding, no
     * quality loss); everything else is converted through GD.
     *
     * @param string $fit cover|contain|stretch
     */
    public function image(
        string $path,
        float $x,
        float $y,
        float $width,
        float $height,
        string $fit = 'cover'
    ): bool {
        $key = $this->prepareImage($path);
        if ($key === null) {
            return false;
        }
        $meta = $this->images[$key];
        $nativeWidth = max(1, $meta['width']);
        $nativeHeight = max(1, $meta['height']);

        $drawWidth = $width;
        $drawHeight = $height;
        $drawX = $x;
        $drawY = $y;

        if ($fit !== 'stretch') {
            $scale = $fit === 'contain'
                ? min($width / $nativeWidth, $height / $nativeHeight)
                : max($width / $nativeWidth, $height / $nativeHeight);
            $drawWidth = $nativeWidth * $scale;
            $drawHeight = $nativeHeight * $scale;
            $drawX = $x + (($width - $drawWidth) / 2);
            $drawY = $y + (($height - $drawHeight) / 2);
        }

        $this->save();
        if ($fit === 'cover') {
            $this->clipRect($x, $y, $width, $height);
        }
        $this->write(
            $this->number($drawWidth) . ' 0 0 ' . $this->number($drawHeight) . ' '
            . $this->number($drawX) . ' ' . $this->number($this->y($drawY) - $drawHeight) . ' cm '
            . '/' . $key . ' Do'
        );
        $this->restore();

        return true;
    }

    /** Place a raw PNG string (used for the generated QR codes). */
    public function imageFromString(string $bytes, float $x, float $y, float $width, float $height): bool
    {
        $temp = \App\Core\Path::tempFile('pdfimg');
        if ($temp === null) {
            return false;
        }
        file_put_contents($temp, $bytes);
        $result = $this->image($temp, $x, $y, $width, $height, 'contain');
        @unlink($temp);
        return $result;
    }

    /** @return string|null resource name of the image XObject */
    private function prepareImage(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $key = 'Im' . substr(md5($path . (string) filemtime($path)), 0, 10);
        if (isset($this->images[$key])) {
            return $key;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }
        [$width, $height, $type] = $info;

        if ($type === IMAGETYPE_JPEG) {
            $data = (string) file_get_contents($path);
            $channels = (int) ($info['channels'] ?? 3);
            $colorSpace = $channels === 4 ? '/DeviceCMYK' : ($channels === 1 ? '/DeviceGray' : '/DeviceRGB');
            $object = $this->addStreamObject(
                $data,
                '/Type /XObject /Subtype /Image /Width ' . $width . ' /Height ' . $height
                . ' /ColorSpace ' . $colorSpace . ' /BitsPerComponent 8 /Filter /DCTDecode',
                false
            );
            $this->images[$key] = ['object' => $object, 'width' => $width, 'height' => $height];
            return $key;
        }

        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $image = @imagecreatefromstring((string) file_get_contents($path));
        if ($image === false) {
            return null;
        }

        // Flatten onto white: PDF image XObjects here are opaque RGB, and an
        // unflattened alpha channel would come out black.
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        $rgb = '';
        for ($py = 0; $py < $height; $py++) {
            for ($px = 0; $px < $width; $px++) {
                $color = imagecolorat($canvas, $px, $py);
                $rgb .= chr(($color >> 16) & 0xFF) . chr(($color >> 8) & 0xFF) . chr($color & 0xFF);
            }
        }
        imagedestroy($canvas);

        $object = $this->addStreamObject(
            $rgb,
            '/Type /XObject /Subtype /Image /Width ' . $width . ' /Height ' . $height
            . ' /ColorSpace /DeviceRGB /BitsPerComponent 8',
            true
        );
        $this->images[$key] = ['object' => $object, 'width' => $width, 'height' => $height];
        return $key;
    }

    // ------------------------------------------------------------------
    //  Output
    // ------------------------------------------------------------------

    public function output(): string
    {
        if ($this->pages === []) {
            $this->addPage();
        }

        // Fonts must be emitted before the page objects reference them.
        $fontObjects = [];
        foreach ($this->fonts as $alias => $entry) {
            $object = $this->emitFont($alias);
            if ($object !== null) {
                $fontObjects[$alias] = $object;
            }
        }

        $pagesObjectNumber = $this->reserveObject();
        $kids = [];

        foreach ($this->pages as $page) {
            $contentObject = $this->addStreamObject($page['content'], '', $this->compress);

            $resources = '<< /ProcSet [/PDF /Text /ImageB /ImageC /ImageI]';
            if ($fontObjects !== []) {
                $resources .= ' /Font << ';
                foreach ($fontObjects as $alias => $object) {
                    $resources .= '/' . $this->fontResourceName($alias) . ' ' . $object . ' 0 R ';
                }
                $resources .= '>>';
            }
            if ($this->images !== []) {
                $resources .= ' /XObject << ';
                foreach ($this->images as $name => $image) {
                    $resources .= '/' . $name . ' ' . $image['object'] . ' 0 R ';
                }
                $resources .= '>>';
            }
            if ($this->alphaStates !== []) {
                $resources .= ' /ExtGState << ';
                foreach ($this->alphaStates as $value => $object) {
                    $resources .= '/GS' . str_replace('.', '', $value) . ' ' . $object . ' 0 R ';
                }
                $resources .= '>>';
            }
            if ($this->shadings !== []) {
                $resources .= ' /Shading << ';
                foreach ($this->shadings as $shading) {
                    $resources .= '/' . $shading['name'] . ' ' . $shading['object'] . ' 0 R ';
                }
                $resources .= '>>';
            }
            $resources .= ' >>';

            $kids[] = $this->addObject(
                '<< /Type /Page /Parent ' . $pagesObjectNumber . ' 0 R'
                . ' /MediaBox [0 0 ' . $this->number($page['width']) . ' ' . $this->number($page['height']) . ']'
                . ' /Resources ' . $resources
                . ' /Contents ' . $contentObject . ' 0 R >>'
            );
        }

        $this->setObject(
            $pagesObjectNumber,
            '<< /Type /Pages /Count ' . count($kids) . ' /Kids ['
            . implode(' ', array_map(static fn ($k) => $k . ' 0 R', $kids)) . '] >>'
        );

        $infoObject = $this->addObject($this->infoDictionary());
        $catalogObject = $this->addObject(
            '<< /Type /Catalog /Pages ' . $pagesObjectNumber . ' 0 R /Lang (en-IN) >>'
        );

        return $this->assemble($catalogObject, $infoObject);
    }

    public function saveTo(string $path): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        return file_put_contents($path, $this->output()) !== false;
    }

    private function infoDictionary(): string
    {
        $out = '<< ';
        foreach ($this->info as $key => $value) {
            if ($value === '') {
                continue;
            }
            $out .= '/' . $key . ' ' . $this->textString($value) . ' ';
        }
        $out .= '/CreationDate (D:' . date('YmdHis') . "+05'30') >>";
        return $out;
    }

    /** UTF-16BE text string with a byte order mark, so Unicode survives. */
    private function textString(string $value): string
    {
        $utf16 = (string) mb_convert_encoding($value, 'UTF-16BE', 'UTF-8');
        return '<FEFF' . strtoupper(bin2hex($utf16)) . '>';
    }

    private function assemble(int $catalogObject, int $infoObject): string
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        // Objects are stored in a zero-indexed list; PDF object numbers start
        // at 1, so the number is always the list index plus one.
        foreach ($this->objects as $index => $body) {
            $number = $index + 1;
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($this->objects) + 1;

        $pdf .= "xref\n0 " . $count . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . $count
            . ' /Root ' . $catalogObject . ' 0 R'
            . ' /Info ' . $infoObject . ' 0 R'
            . ' /ID [<' . md5($pdf, false) . '> <' . md5($pdf, false) . ">] >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF\n";

        return $pdf;
    }

    // ------------------------------------------------------------------
    //  Font embedding
    // ------------------------------------------------------------------

    /** @return int|null the Type0 font object number */
    private function emitFont(string $alias): ?int
    {
        $entry = $this->fonts[$alias];
        if ($entry['used'] === []) {
            return null; // font registered but never used - do not embed it
        }

        $font = $entry['font'];
        try {
            $subset = $font->subset(array_keys($entry['used']));
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('Font subsetting failed, embedding the full font: ' . $e->getMessage());
            $subset = ['font' => $font->raw(), 'map' => []];
        }

        $glyphMap = $subset['map'];
        $useSubsetIndices = $glyphMap !== [];

        // Rewrite the already-written glyph ids to subset indices.
        if ($useSubsetIndices) {
            $this->remapGlyphIds($alias, $glyphMap);
        }

        $fontFile = $this->addStreamObject(
            $subset['font'],
            '/Length1 ' . strlen($subset['font']),
            $this->compress
        );

        $flags = 4; // symbolic
        if ($font->isFixedPitch) {
            $flags |= 1;
        }
        if ($font->italicAngle !== 0) {
            $flags |= 64;
        }

        $descriptor = $this->addObject(
            '<< /Type /FontDescriptor /FontName /' . $this->subsetTag($alias) . '+' . $font->postScriptName
            . ' /Flags ' . $flags
            . ' /FontBBox [' . $font->scaled($font->xMin) . ' ' . $font->scaled($font->yMin) . ' '
            . $font->scaled($font->xMax) . ' ' . $font->scaled($font->yMax) . ']'
            . ' /ItalicAngle ' . (int) $font->italicAngle
            . ' /Ascent ' . $font->scaled($font->ascender)
            . ' /Descent ' . $font->scaled($font->descender)
            . ' /CapHeight ' . $font->scaled($font->capHeight)
            . ' /StemV ' . max(50, (int) round($font->weightClass / 6))
            . ' /FontFile2 ' . $fontFile . ' 0 R >>'
        );

        // Widths, expressed in the CID space actually used.
        $widths = [];
        foreach ($this->fonts[$alias]['used'] as $originalGid => $codePoint) {
            $cid = $useSubsetIndices ? ($glyphMap[$originalGid] ?? 0) : $originalGid;
            $widths[$cid] = $font->widthOfGlyph($originalGid);
        }
        ksort($widths);

        $cidFont = $this->addObject(
            '<< /Type /Font /Subtype /CIDFontType2'
            . ' /BaseFont /' . $this->subsetTag($alias) . '+' . $font->postScriptName
            . ' /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>'
            . ' /FontDescriptor ' . $descriptor . ' 0 R'
            . ' /DW 1000 /W [' . $this->widthArray($widths) . ']'
            . ' /CIDToGIDMap /Identity >>'
        );

        $toUnicode = $this->addStreamObject(
            $this->toUnicodeCMap($alias, $glyphMap, $useSubsetIndices),
            '',
            $this->compress
        );

        return $this->addObject(
            '<< /Type /Font /Subtype /Type0'
            . ' /BaseFont /' . $this->subsetTag($alias) . '+' . $font->postScriptName
            . ' /Encoding /Identity-H'
            . ' /DescendantFonts [' . $cidFont . ' 0 R]'
            . ' /ToUnicode ' . $toUnicode . ' 0 R >>'
        );
    }

    /**
     * Page content already contains the original glyph ids; swap them for the
     * subset indices now that the mapping is known.
     *
     * @param array<int,int> $glyphMap
     */
    private function remapGlyphIds(string $alias, array $glyphMap): void
    {
        $resource = $this->fontResourceName($alias);
        $pattern = '/\/' . preg_quote($resource, '/') . ' [\d.]+ Tf.*?T[jJ]/s';

        foreach ($this->pages as $index => $page) {
            $this->pages[$index]['content'] = (string) preg_replace_callback(
                $pattern,
                static function (array $match) use ($glyphMap): string {
                    return (string) preg_replace_callback(
                        '/<([0-9A-Fa-f]+)>/',
                        static function (array $hexMatch) use ($glyphMap): string {
                            $hex = $hexMatch[1];
                            $out = '';
                            for ($i = 0; $i + 4 <= strlen($hex); $i += 4) {
                                $gid = (int) hexdec(substr($hex, $i, 4));
                                $out .= sprintf('%04X', $glyphMap[$gid] ?? 0);
                            }
                            return '<' . $out . '>';
                        },
                        $match[0]
                    );
                },
                $page['content']
            );
        }
    }

    /** @param array<int,int> $widths cid => width */
    private function widthArray(array $widths): string
    {
        $parts = [];
        $run = [];
        $runStart = null;
        $previous = null;

        foreach ($widths as $cid => $width) {
            if ($previous !== null && $cid === $previous + 1) {
                $run[] = $width;
            } else {
                if ($runStart !== null) {
                    $parts[] = $runStart . ' [' . implode(' ', $run) . ']';
                }
                $runStart = $cid;
                $run = [$width];
            }
            $previous = $cid;
        }
        if ($runStart !== null) {
            $parts[] = $runStart . ' [' . implode(' ', $run) . ']';
        }

        return implode(' ', $parts);
    }

    /**
     * ToUnicode CMap: makes the PDF text selectable, searchable and
     * accessible, which also means pdftotext can verify our output.
     *
     * @param array<int,int> $glyphMap
     */
    private function toUnicodeCMap(string $alias, array $glyphMap, bool $useSubsetIndices): string
    {
        $pairs = [];
        foreach ($this->fonts[$alias]['used'] as $originalGid => $codePoint) {
            $cid = $useSubsetIndices ? ($glyphMap[$originalGid] ?? 0) : $originalGid;
            $pairs[$cid] = $codePoint;
        }
        ksort($pairs);

        $chunks = array_chunk($pairs, 100, true);
        $body = '';
        foreach ($chunks as $chunk) {
            $body .= count($chunk) . " beginbfchar\n";
            foreach ($chunk as $cid => $codePoint) {
                $body .= sprintf("<%04X> <%s>\n", $cid, $this->utf16Hex($codePoint));
            }
            $body .= "endbfchar\n";
        }

        return "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\nbegincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            . "/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n"
            . "1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n"
            . $body
            . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
    }

    private function utf16Hex(int $codePoint): string
    {
        if ($codePoint <= 0xFFFF) {
            return sprintf('%04X', $codePoint);
        }
        $value = $codePoint - 0x10000;
        return sprintf('%04X%04X', 0xD800 + ($value >> 10), 0xDC00 + ($value & 0x3FF));
    }

    private function subsetTag(string $alias): string
    {
        // Six uppercase letters, as the specification requires.
        $hash = md5($alias);
        $tag = '';
        for ($i = 0; $i < 6; $i++) {
            $tag .= chr(65 + (hexdec($hash[$i]) % 26));
        }
        return $tag;
    }

    private function fontResourceName(string $alias): string
    {
        return 'F' . substr(md5($alias), 0, 8);
    }

    // ------------------------------------------------------------------
    //  Object helpers
    // ------------------------------------------------------------------

    private function addObject(string $body): int
    {
        $this->objects[] = $body;
        return array_key_last($this->objects) + 1;
    }

    private function reserveObject(): int
    {
        $this->objects[] = '<< >>';
        return array_key_last($this->objects) + 1;
    }

    private function setObject(int $number, string $body): void
    {
        $this->objects[$number - 1] = $body;
    }

    private function addStreamObject(string $data, string $extraDict = '', bool $compress = true): int
    {
        $filters = [];
        if ($compress && function_exists('gzcompress') && $data !== '') {
            $compressed = gzcompress($data, 6);
            if ($compressed !== false && strlen($compressed) < strlen($data)) {
                $data = $compressed;
                $filters[] = '/FlateDecode';
            }
        }

        $dict = '<< ';
        if ($extraDict !== '') {
            $dict .= $extraDict . ' ';
        }
        if ($filters !== []) {
            $dict .= '/Filter ' . (count($filters) === 1 ? $filters[0] : '[' . implode(' ', $filters) . ']') . ' ';
        }
        $dict .= '/Length ' . strlen($data) . ' >>';

        return $this->addObject($dict . "\nstream\n" . $data . "\nendstream");
    }

    // ------------------------------------------------------------------
    //  Colour and number formatting
    // ------------------------------------------------------------------

    private function colorOperator(string $hex, bool $forText, bool $stroke = false): string
    {
        [$r, $g, $b] = self::hexToRgbFloat($hex);
        $operator = $stroke ? 'RG' : 'rg';
        return $this->number($r) . ' ' . $this->number($g) . ' ' . $this->number($b) . ' ' . $operator;
    }

    /** @return array{0:float,1:float,2:float} */
    public static function hexToRgbFloat(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) === 8) {
            $hex = substr($hex, 0, 6);
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [0.0, 0.0, 0.0];
        }
        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    private function number(float $value): string
    {
        if (abs($value - round($value)) < 0.0001) {
            return (string) (int) round($value);
        }
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
