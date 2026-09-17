<?php

declare(strict_types=1);

namespace App\Core\Pdf;

/**
 * Minimal TrueType/OpenType parser and subsetter.
 *
 * Enough of the specification to embed a font in a PDF as a CIDFontType2:
 * metrics, a Unicode to glyph map, and a rebuilt `glyf`/`loca` pair
 * containing only the glyphs a document actually uses. Subsetting keeps a
 * three-script invitation PDF in the tens of kilobytes instead of ~800 KB.
 */
final class TrueTypeFont
{
    /** @var array<string,array{offset:int,length:int}> */
    private array $tables = [];

    private string $data;

    public int $unitsPerEm = 1000;
    public int $numGlyphs = 0;
    public int $ascender = 800;
    public int $descender = -200;
    public int $capHeight = 700;
    public int $italicAngle = 0;
    public int $weightClass = 400;
    public int $xMin = 0;
    public int $yMin = 0;
    public int $xMax = 1000;
    public int $yMax = 1000;
    public bool $isFixedPitch = false;
    public string $postScriptName = 'EmbeddedFont';

    /** @var array<int,int> advance widths in font units, by glyph id */
    private array $advanceWidths = [];
    /** @var array<int,int> left side bearings in font units, by glyph id */
    private array $leftSideBearings = [];
    /** @var array<int,int> unicode code point => glyph id */
    private array $cmap = [];
    private int $indexToLocFormat = 0;
    /** @var array<int,int>|null */
    private ?array $loca = null;
    /** @var array<int,array{xMin:int,yMin:int,xMax:int,yMax:int}> */
    private array $bboxCache = [];

    private function __construct(string $data)
    {
        $this->data = $data;
        $this->parse();
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Font file not readable: ' . basename($path));
        }
        $data = (string) file_get_contents($path);
        if (strlen($data) < 12) {
            throw new \RuntimeException('Font file is too small to be valid.');
        }
        return new self($data);
    }

    public static function fromString(string $data): self
    {
        return new self($data);
    }

    // ------------------------------------------------------------------
    //  Parsing
    // ------------------------------------------------------------------

    private function parse(): void
    {
        $tag = substr($this->data, 0, 4);
        if ($tag === 'ttcf') {
            // Font collection: use the first font it contains.
            $offset = $this->readULong(12);
            $this->readTableDirectory($offset);
        } else {
            if (!in_array($tag, ["\x00\x01\x00\x00", 'true', 'OTTO', 'typ1'], true)) {
                throw new \RuntimeException('Unsupported font format.');
            }
            $this->readTableDirectory(0);
        }

        if ($tag === 'OTTO' || isset($this->tables['CFF '])) {
            // CFF outlines cannot be subsetted by this parser.
            throw new \RuntimeException('OpenType/CFF fonts are not supported; use a TrueType (glyf) font.');
        }

        $this->readHead();
        $this->readMaxp();
        $this->readHhea();
        $this->readOs2();
        $this->readPost();
        $this->readHmtx();
        $this->readCmap();
        $this->readName();
    }

    private function readTableDirectory(int $base): void
    {
        $numTables = $this->readUShort($base + 4);
        for ($i = 0; $i < $numTables; $i++) {
            $record = $base + 12 + ($i * 16);
            $tag = substr($this->data, $record, 4);
            $this->tables[$tag] = [
                'offset' => $this->readULong($record + 8),
                'length' => $this->readULong($record + 12),
            ];
        }
    }

    private function table(string $tag): ?array
    {
        return $this->tables[$tag] ?? null;
    }

    private function tableData(string $tag): string
    {
        $table = $this->table($tag);
        if ($table === null) {
            return '';
        }
        return substr($this->data, $table['offset'], $table['length']);
    }

    private function readHead(): void
    {
        $head = $this->table('head');
        if ($head === null) {
            throw new \RuntimeException('Font is missing the head table.');
        }
        $o = $head['offset'];
        $this->unitsPerEm = max(16, $this->readUShort($o + 18));
        $this->xMin = $this->readShort($o + 36);
        $this->yMin = $this->readShort($o + 38);
        $this->xMax = $this->readShort($o + 40);
        $this->yMax = $this->readShort($o + 42);
        $this->indexToLocFormat = $this->readShort($o + 50);
    }

    private function readMaxp(): void
    {
        $maxp = $this->table('maxp');
        $this->numGlyphs = $maxp === null ? 0 : $this->readUShort($maxp['offset'] + 4);
    }

    private function readHhea(): void
    {
        $hhea = $this->table('hhea');
        if ($hhea === null) {
            return;
        }
        $this->ascender = $this->readShort($hhea['offset'] + 4);
        $this->descender = $this->readShort($hhea['offset'] + 6);
    }

    private function readOs2(): void
    {
        $os2 = $this->table('OS/2');
        if ($os2 === null) {
            $this->capHeight = (int) round($this->ascender * 0.7);
            return;
        }
        $o = $os2['offset'];
        $version = $this->readUShort($o);
        $this->weightClass = $this->readUShort($o + 4);
        $typoAscender = $this->readShort($o + 68);
        $typoDescender = $this->readShort($o + 70);
        if ($typoAscender !== 0) {
            $this->ascender = $typoAscender;
        }
        if ($typoDescender !== 0) {
            $this->descender = $typoDescender;
        }
        $this->capHeight = $version >= 2
            ? $this->readShort($o + 88)
            : (int) round($this->ascender * 0.7);
        if ($this->capHeight <= 0) {
            $this->capHeight = (int) round($this->ascender * 0.7);
        }
    }

    private function readPost(): void
    {
        $post = $this->table('post');
        if ($post === null) {
            return;
        }
        $o = $post['offset'];
        // italicAngle is a 16.16 fixed point value.
        $this->italicAngle = $this->readShort($o + 4);
        $this->isFixedPitch = $this->readULong($o + 16) !== 0;
    }

    private function readHmtx(): void
    {
        $hhea = $this->table('hhea');
        $hmtx = $this->table('hmtx');
        if ($hhea === null || $hmtx === null) {
            return;
        }
        $numberOfHMetrics = max(1, $this->readUShort($hhea['offset'] + 34));
        $o = $hmtx['offset'];
        $last = 0;
        for ($gid = 0; $gid < $this->numGlyphs; $gid++) {
            if ($gid < $numberOfHMetrics) {
                $last = $this->readUShort($o + ($gid * 4));
                $this->leftSideBearings[$gid] = $this->readShort($o + ($gid * 4) + 2);
            } else {
                // Monospaced tail: advances repeat, bearings continue in a
                // separate int16 array. Dropping these shifts every glyph
                // that lives past numberOfHMetrics - which, in an Indic font,
                // is most of the combining marks.
                $this->leftSideBearings[$gid] = $this->readShort(
                    $o + ($numberOfHMetrics * 4) + (($gid - $numberOfHMetrics) * 2)
                );
            }
            $this->advanceWidths[$gid] = $last;
        }
    }

    private function readName(): void
    {
        $name = $this->table('name');
        if ($name === null) {
            return;
        }
        $o = $name['offset'];
        $count = $this->readUShort($o + 2);
        $stringOffset = $this->readUShort($o + 4);
        for ($i = 0; $i < $count; $i++) {
            $record = $o + 6 + ($i * 12);
            $nameId = $this->readUShort($record + 6);
            if ($nameId !== 6) { // PostScript name
                continue;
            }
            $platformId = $this->readUShort($record);
            $length = $this->readUShort($record + 8);
            $offset = $this->readUShort($record + 10);
            $raw = substr($this->data, $o + $stringOffset + $offset, $length);
            $value = $platformId === 3 || $platformId === 0
                ? (string) mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE')
                : $raw;
            $value = preg_replace('/[^A-Za-z0-9\-+]/', '', $value) ?? '';
            if ($value !== '') {
                $this->postScriptName = substr($value, 0, 60);
                return;
            }
        }
    }

    /** Character to glyph map: formats 4, 6 and 12. */
    private function readCmap(): void
    {
        $cmap = $this->table('cmap');
        if ($cmap === null) {
            return;
        }
        $base = $cmap['offset'];
        $numTables = $this->readUShort($base + 2);

        $best = null;
        $bestScore = -1;
        for ($i = 0; $i < $numTables; $i++) {
            $record = $base + 4 + ($i * 8);
            $platformId = $this->readUShort($record);
            $encodingId = $this->readUShort($record + 2);
            $offset = $this->readULong($record + 4);

            // Prefer full-repertoire Unicode subtables.
            $score = match (true) {
                $platformId === 3 && $encodingId === 10 => 5,
                $platformId === 0 && $encodingId === 4  => 5,
                $platformId === 0 && $encodingId === 6  => 5,
                $platformId === 3 && $encodingId === 1  => 4,
                $platformId === 0 && $encodingId === 3  => 4,
                $platformId === 0                       => 3,
                $platformId === 3 && $encodingId === 0  => 2,
                default                                 => 1,
            };
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $base + $offset;
            }
        }

        if ($best === null) {
            return;
        }

        $format = $this->readUShort($best);
        match ($format) {
            4       => $this->readCmapFormat4($best),
            6       => $this->readCmapFormat6($best),
            12      => $this->readCmapFormat12($best),
            0       => $this->readCmapFormat0($best),
            default => null,
        };
    }

    private function readCmapFormat0(int $offset): void
    {
        for ($code = 0; $code < 256; $code++) {
            $gid = ord($this->data[$offset + 6 + $code] ?? "\0");
            if ($gid !== 0) {
                $this->cmap[$code] = $gid;
            }
        }
    }

    private function readCmapFormat4(int $offset): void
    {
        $segCountX2 = $this->readUShort($offset + 6);
        $segCount = intdiv($segCountX2, 2);
        $endCodes = $offset + 14;
        $startCodes = $endCodes + $segCountX2 + 2;
        $idDeltas = $startCodes + $segCountX2;
        $idRangeOffsets = $idDeltas + $segCountX2;

        for ($seg = 0; $seg < $segCount; $seg++) {
            $end = $this->readUShort($endCodes + ($seg * 2));
            $start = $this->readUShort($startCodes + ($seg * 2));
            $delta = $this->readShort($idDeltas + ($seg * 2));
            $rangeOffsetPos = $idRangeOffsets + ($seg * 2);
            $rangeOffset = $this->readUShort($rangeOffsetPos);

            if ($start === 0xFFFF) {
                continue;
            }
            for ($code = $start; $code <= $end && $code !== 0x10000; $code++) {
                if ($rangeOffset === 0) {
                    $gid = ($code + $delta) & 0xFFFF;
                } else {
                    $glyphIndexAddress = $rangeOffsetPos + $rangeOffset + (($code - $start) * 2);
                    $gid = $this->readUShort($glyphIndexAddress);
                    if ($gid !== 0) {
                        $gid = ($gid + $delta) & 0xFFFF;
                    }
                }
                if ($gid !== 0) {
                    $this->cmap[$code] = $gid;
                }
            }
        }
    }

    private function readCmapFormat6(int $offset): void
    {
        $first = $this->readUShort($offset + 6);
        $count = $this->readUShort($offset + 8);
        for ($i = 0; $i < $count; $i++) {
            $gid = $this->readUShort($offset + 10 + ($i * 2));
            if ($gid !== 0) {
                $this->cmap[$first + $i] = $gid;
            }
        }
    }

    private function readCmapFormat12(int $offset): void
    {
        $numGroups = $this->readULong($offset + 12);
        // Guard against a malformed table claiming millions of groups.
        $numGroups = min($numGroups, 200000);
        for ($i = 0; $i < $numGroups; $i++) {
            $group = $offset + 16 + ($i * 12);
            $start = $this->readULong($group);
            $end = $this->readULong($group + 4);
            $startGid = $this->readULong($group + 8);
            if ($end - $start > 65536) {
                $end = $start + 65536;
            }
            for ($code = $start; $code <= $end; $code++) {
                $this->cmap[$code] = $startGid + ($code - $start);
            }
        }
    }

    // ------------------------------------------------------------------
    //  Public metrics API
    // ------------------------------------------------------------------

    /** Glyph id for a Unicode code point (0 when unmapped). */
    public function glyphFor(int $codePoint): int
    {
        return $this->cmap[$codePoint] ?? 0;
    }

    public function hasGlyph(int $codePoint): bool
    {
        return isset($this->cmap[$codePoint]) && $this->cmap[$codePoint] !== 0;
    }

    /** Left side bearing in 1/1000 em units. */
    public function leftSideBearing(int $gid): int
    {
        return $this->scaled($this->leftSideBearings[$gid] ?? 0);
    }

    /** Advance width in 1/1000 em units (the PDF text space unit). */
    public function widthOfGlyph(int $gid): int
    {
        $units = $this->advanceWidths[$gid] ?? $this->advanceWidths[0] ?? 0;
        return (int) round($units * 1000 / $this->unitsPerEm);
    }

    public function scaled(int $value): int
    {
        return (int) round($value * 1000 / $this->unitsPerEm);
    }

    /**
     * Ink bounding box of a glyph, scaled to 1/1000 em.
     *
     * Needed to centre a combining mark over its base: a zero-advance mark
     * is drawn relative to its own origin, which is rarely its ink centre.
     *
     * @return array{xMin:int,yMin:int,xMax:int,yMax:int}
     */
    public function glyphBBox(int $gid): array
    {
        if (isset($this->bboxCache[$gid])) {
            return $this->bboxCache[$gid];
        }
        $empty = ['xMin' => 0, 'yMin' => 0, 'xMax' => 0, 'yMax' => 0];

        $loca = $this->locaTable();
        $glyf = $this->table('glyf');
        if ($loca === null || $glyf === null || $gid < 0 || $gid >= $this->numGlyphs) {
            return $this->bboxCache[$gid] = $empty;
        }
        $start = $loca[$gid] ?? 0;
        $end = $loca[$gid + 1] ?? $start;
        if ($end - $start < 10) {
            return $this->bboxCache[$gid] = $empty; // empty glyph (e.g. space)
        }
        $base = $glyf['offset'] + $start;

        return $this->bboxCache[$gid] = [
            'xMin' => $this->scaled($this->readShort($base + 2)),
            'yMin' => $this->scaled($this->readShort($base + 4)),
            'xMax' => $this->scaled($this->readShort($base + 6)),
            'yMax' => $this->scaled($this->readShort($base + 8)),
        ];
    }

    /** Horizontal centre of a glyph's ink, in 1/1000 em. */
    public function glyphInkCentre(int $gid): int
    {
        $box = $this->glyphBBox($gid);
        return (int) round(($box['xMin'] + $box['xMax']) / 2);
    }

    /** @return array<int,int> */
    public function coverage(): array
    {
        return $this->cmap;
    }

    // ------------------------------------------------------------------
    //  Subsetting
    // ------------------------------------------------------------------

    /**
     * Build a subset font containing only the requested glyphs, re-indexed
     * from zero. Glyph 0 (.notdef) is always included first.
     *
     * @param array<int,int> $gids glyph ids to keep
     * @return array{font:string,map:array<int,int>} map is oldGid => newGid
     */
    public function subset(array $gids): array
    {
        $loca = $this->locaTable();
        if ($loca === null) {
            // No glyf outlines to subset (should not happen for TrueType).
            throw new \RuntimeException('Font has no loca/glyf tables.');
        }

        $needed = [0 => true];
        foreach ($gids as $gid) {
            if ($gid >= 0 && $gid < $this->numGlyphs) {
                $needed[$gid] = true;
            }
        }

        // Composite glyphs reference other glyphs; pull those in too.
        $queue = array_keys($needed);
        while ($queue !== []) {
            $gid = (int) array_pop($queue);
            foreach ($this->compositeComponents($gid, $loca) as $component) {
                if (!isset($needed[$component]) && $component < $this->numGlyphs) {
                    $needed[$component] = true;
                    $queue[] = $component;
                }
            }
        }

        $ordered = array_keys($needed);
        sort($ordered, SORT_NUMERIC);

        $map = [];
        foreach ($ordered as $newGid => $oldGid) {
            $map[$oldGid] = $newGid;
        }

        // ---- glyf + loca ----
        $glyfTable = $this->table('glyf');
        $glyfBase = $glyfTable === null ? 0 : $glyfTable['offset'];
        $glyf = '';
        $offsets = [0];
        foreach ($ordered as $oldGid) {
            $start = $loca[$oldGid] ?? 0;
            $end = $loca[$oldGid + 1] ?? $start;
            $glyphData = $end > $start ? substr($this->data, $glyfBase + $start, $end - $start) : '';
            if ($glyphData !== '') {
                $glyphData = $this->remapComposite($glyphData, $map);
            }
            // Glyph data must be aligned to a 2-byte boundary.
            if (strlen($glyphData) % 2 !== 0) {
                $glyphData .= "\x00";
            }
            $glyf .= $glyphData;
            $offsets[] = strlen($glyf);
        }

        $useLongLoca = end($offsets) > 0x1FFFF;
        $locaData = '';
        foreach ($offsets as $offset) {
            $locaData .= $useLongLoca
                ? pack('N', $offset)
                : pack('n', intdiv($offset, 2));
        }

        // ---- head (with the loca format we just chose) ----
        $head = $this->tableData('head');
        if ($head === '') {
            throw new \RuntimeException('Font is missing the head table.');
        }
        $head = substr_replace($head, pack('n', $useLongLoca ? 1 : 0), 50, 2);
        // Clear checkSumAdjustment; PDF readers do not verify it.
        $head = substr_replace($head, pack('N', 0), 8, 4);

        // ---- hmtx + hhea ----
        $hmtx = '';
        foreach ($ordered as $oldGid) {
            // Both values matter: the advance positions the next glyph and the
            // left side bearing positions this glyph's outline.
            $hmtx .= pack('n', $this->advanceWidths[$oldGid] ?? 0)
                . pack('n', ($this->leftSideBearings[$oldGid] ?? 0) & 0xFFFF);
        }
        $hhea = $this->tableData('hhea');
        if ($hhea !== '') {
            $hhea = substr_replace($hhea, pack('n', count($ordered)), 34, 2);
        }

        // ---- maxp ----
        $maxp = $this->tableData('maxp');
        if ($maxp !== '') {
            $maxp = substr_replace($maxp, pack('n', count($ordered)), 4, 2);
        }

        $tables = [
            'head' => $head,
            'hhea' => $hhea,
            'maxp' => $maxp,
            'hmtx' => $hmtx,
            'loca' => $locaData,
            'glyf' => $glyf,
        ];
        // Hinting and metrics tables are copied when present; they are glyph
        // index independent (or unused by PDF renderers).
        foreach (['cvt ', 'fpgm', 'prep', 'OS/2'] as $tag) {
            $copied = $this->tableData($tag);
            if ($copied !== '') {
                $tables[$tag] = $copied;
            }
        }
        // A version 3.0 post table has no glyph names, so it stays valid.
        $tables['post'] = pack('N', 0x00030000) . str_repeat("\x00", 28);

        return ['font' => $this->buildFont($tables), 'map' => $map];
    }

    /** @return array<int,int>|null glyph offsets into glyf */
    private function locaTable(): ?array
    {
        if ($this->loca !== null) {
            return $this->loca;
        }
        $loca = $this->table('loca');
        if ($loca === null || $this->table('glyf') === null) {
            return null;
        }
        $offsets = [];
        $o = $loca['offset'];
        for ($i = 0; $i <= $this->numGlyphs; $i++) {
            $offsets[$i] = $this->indexToLocFormat === 0
                ? $this->readUShort($o + ($i * 2)) * 2
                : $this->readULong($o + ($i * 4));
        }
        return $this->loca = $offsets;
    }

    /**
     * Glyph ids referenced by a composite glyph.
     *
     * @param array<int,int> $loca
     * @return array<int,int>
     */
    private function compositeComponents(int $gid, array $loca): array
    {
        $glyf = $this->table('glyf');
        if ($glyf === null) {
            return [];
        }
        $start = $loca[$gid] ?? 0;
        $end = $loca[$gid + 1] ?? $start;
        if ($end - $start < 10) {
            return [];
        }
        $base = $glyf['offset'] + $start;
        $numberOfContours = $this->readShort($base);
        if ($numberOfContours >= 0) {
            return []; // simple glyph
        }

        $components = [];
        $position = $base + 10;
        $limit = $glyf['offset'] + $end;
        while ($position + 4 <= $limit) {
            $flags = $this->readUShort($position);
            $glyphIndex = $this->readUShort($position + 2);
            $components[] = $glyphIndex;
            $position += 4;

            $position += ($flags & 0x0001) ? 4 : 2;      // ARG_1_AND_2_ARE_WORDS
            if ($flags & 0x0008) {                        // WE_HAVE_A_SCALE
                $position += 2;
            } elseif ($flags & 0x0040) {                  // X_AND_Y_SCALE
                $position += 4;
            } elseif ($flags & 0x0080) {                  // TWO_BY_TWO
                $position += 8;
            }
            if (!($flags & 0x0020)) {                     // MORE_COMPONENTS
                break;
            }
        }
        return $components;
    }

    /**
     * Rewrite the component glyph ids inside a composite glyph so they point
     * at the subset indices.
     *
     * @param array<int,int> $map
     */
    private function remapComposite(string $glyphData, array $map): string
    {
        if (strlen($glyphData) < 10) {
            return $glyphData;
        }
        $numberOfContours = unpack('n', substr($glyphData, 0, 2))[1];
        if ($numberOfContours < 0x8000) {
            return $glyphData; // simple glyph (non-negative int16)
        }

        $position = 10;
        $length = strlen($glyphData);
        while ($position + 4 <= $length) {
            $flags = unpack('n', substr($glyphData, $position, 2))[1];
            $oldIndex = unpack('n', substr($glyphData, $position + 2, 2))[1];
            $newIndex = $map[$oldIndex] ?? 0;
            $glyphData = substr_replace($glyphData, pack('n', $newIndex), $position + 2, 2);
            $position += 4;

            $position += ($flags & 0x0001) ? 4 : 2;
            if ($flags & 0x0008) {
                $position += 2;
            } elseif ($flags & 0x0040) {
                $position += 4;
            } elseif ($flags & 0x0080) {
                $position += 8;
            }
            if (!($flags & 0x0020)) {
                break;
            }
        }
        return $glyphData;
    }

    /**
     * Assemble an sfnt container from the given tables.
     *
     * @param array<string,string> $tables
     */
    private function buildFont(array $tables): string
    {
        $tables = array_filter($tables, static fn (string $data): bool => $data !== '');
        ksort($tables);

        $numTables = count($tables);
        $searchRange = 1;
        $entrySelector = 0;
        while ($searchRange * 2 <= $numTables) {
            $searchRange *= 2;
            $entrySelector++;
        }
        $searchRange *= 16;

        $header = pack('N', 0x00010000)
            . pack('n', $numTables)
            . pack('n', $searchRange)
            . pack('n', $entrySelector)
            . pack('n', $numTables * 16 - $searchRange);

        $directory = '';
        $body = '';
        $offset = 12 + ($numTables * 16);

        foreach ($tables as $tag => $data) {
            $padded = $data . str_repeat("\x00", (4 - (strlen($data) % 4)) % 4);
            $directory .= str_pad(substr($tag, 0, 4), 4)
                . pack('N', $this->checksum($padded))
                . pack('N', $offset)
                . pack('N', strlen($data));
            $body .= $padded;
            $offset += strlen($padded);
        }

        return $header . $directory . $body;
    }

    private function checksum(string $data): int
    {
        $sum = 0;
        $count = intdiv(strlen($data), 4);
        for ($i = 0; $i < $count; $i++) {
            $sum = ($sum + unpack('N', substr($data, $i * 4, 4))[1]) & 0xFFFFFFFF;
        }
        return $sum;
    }

    /** The complete font file, for callers that prefer full embedding. */
    public function raw(): string
    {
        return $this->data;
    }

    // ------------------------------------------------------------------
    //  Binary readers
    // ------------------------------------------------------------------

    private function readUShort(int $offset): int
    {
        if ($offset + 2 > strlen($this->data)) {
            return 0;
        }
        return unpack('n', substr($this->data, $offset, 2))[1];
    }

    private function readShort(int $offset): int
    {
        $value = $this->readUShort($offset);
        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private function readULong(int $offset): int
    {
        if ($offset + 4 > strlen($this->data)) {
            return 0;
        }
        return unpack('N', substr($this->data, $offset, 4))[1];
    }
}
