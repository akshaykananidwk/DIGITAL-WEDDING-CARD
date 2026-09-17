<?php

declare(strict_types=1);

namespace App\Seeds;

/**
 * Design tokens.
 *
 * A palette plus a layout plus an ornament is a template. Keeping them as
 * data is what lets the catalogue grow to thousands of designs without a
 * single new PHP file.
 */
final class ThemePalettes
{
    /**
     * slug => tokens
     *
     * @return array<string,array<string,string>>
     */
    public static function all(): array
    {
        return [
            'kumkum-red' => [
                'label'      => 'Kumkum Red',
                'primary'    => '#C8102E',
                'secondary'  => '#F0B429',
                'background' => '#FFF8EE',
                'surface'    => '#FFFFFF',
                'text'       => '#3D2B1F',
                'muted'      => '#7A6A55',
                'accent'     => '#E0A400',
                'ornament'   => 'paisley',
            ],
            'maroon-gold' => [
                'label'      => 'Maroon & Gold',
                'primary'    => '#7B1E3A',
                'secondary'  => '#D4AF37',
                'background' => '#FDF6EC',
                'surface'    => '#FFFFFF',
                'text'       => '#3A2A2F',
                'muted'      => '#7C6A6F',
                'accent'     => '#B8860B',
                'ornament'   => 'mandala',
            ],
            'krishna-blue' => [
                'label'      => 'Krishna Blue',
                'primary'    => '#1D4E89',
                'secondary'  => '#F2C14E',
                'background' => '#F3F8FD',
                'surface'    => '#FFFFFF',
                'text'       => '#1B2A3A',
                'muted'      => '#5D7385',
                'accent'     => '#3E92CC',
                'ornament'   => 'peacock',
            ],
            'peacock-teal' => [
                'label'      => 'Peacock Teal',
                'primary'    => '#0F766E',
                'secondary'  => '#F4B95F',
                'background' => '#F1FAF8',
                'surface'    => '#FFFFFF',
                'text'       => '#123330',
                'muted'      => '#5C7C78',
                'accent'     => '#14B8A6',
                'ornament'   => 'peacock',
            ],
            'marigold' => [
                'label'      => 'Marigold',
                'primary'    => '#E07A15',
                'secondary'  => '#C8102E',
                'background' => '#FFF7E8',
                'surface'    => '#FFFFFF',
                'text'       => '#43301A',
                'muted'      => '#8A7150',
                'accent'     => '#FFB020',
                'ornament'   => 'floral',
            ],
            'rose-blush' => [
                'label'      => 'Rose Blush',
                'primary'    => '#B03A5B',
                'secondary'  => '#E8B4BC',
                'background' => '#FFF5F7',
                'surface'    => '#FFFFFF',
                'text'       => '#3E2730',
                'muted'      => '#8C6B74',
                'accent'     => '#D98198',
                'ornament'   => 'floral',
            ],
            'royal-purple' => [
                'label'      => 'Royal Purple',
                'primary'    => '#5B21B6',
                'secondary'  => '#D4AF37',
                'background' => '#F8F5FF',
                'surface'    => '#FFFFFF',
                'text'       => '#2A1B47',
                'muted'      => '#6E5C93',
                'accent'     => '#8B5CF6',
                'ornament'   => 'arch',
            ],
            'emerald-jade' => [
                'label'      => 'Emerald Jade',
                'primary'    => '#186A3B',
                'secondary'  => '#E9C46A',
                'background' => '#F2FAF4',
                'surface'    => '#FFFFFF',
                'text'       => '#17321F',
                'muted'      => '#5D7C66',
                'accent'     => '#2E9E5B',
                'ornament'   => 'leaf',
            ],
            'ivory-minimal' => [
                'label'      => 'Ivory Minimal',
                'primary'    => '#2F2A25',
                'secondary'  => '#C2A878',
                'background' => '#FBF9F5',
                'surface'    => '#FFFFFF',
                'text'       => '#2F2A25',
                'muted'      => '#8A8177',
                'accent'     => '#C2A878',
                'ornament'   => 'line',
            ],
            'midnight-gold' => [
                'label'      => 'Midnight & Gold',
                'primary'    => '#D4AF37',
                'secondary'  => '#F5E6C8',
                'background' => '#141118',
                'surface'    => '#201A28',
                'text'       => '#F6EFE2',
                'muted'      => '#B6A78E',
                'accent'     => '#E7C86A',
                'ornament'   => 'mandala',
            ],
            'saffron-white' => [
                'label'      => 'Saffron & White',
                'primary'    => '#D97706',
                'secondary'  => '#7C2D12',
                'background' => '#FFFBF3',
                'surface'    => '#FFFFFF',
                'text'       => '#3B2A15',
                'muted'      => '#8B7355',
                'accent'     => '#F59E0B',
                'ornament'   => 'temple',
            ],
            'sandalwood' => [
                'label'      => 'Sandalwood',
                'primary'    => '#8B5E34',
                'secondary'  => '#DDA15E',
                'background' => '#FEFAE0',
                'surface'    => '#FFFFFF',
                'text'       => '#3F2D1B',
                'muted'      => '#7F6A50',
                'accent'     => '#BC6C25',
                'ornament'   => 'paisley',
            ],
            'lotus-pink' => [
                'label'      => 'Lotus Pink',
                'primary'    => '#DB2777',
                'secondary'  => '#FBCFE8',
                'background' => '#FFF5FA',
                'surface'    => '#FFFFFF',
                'text'       => '#3D1A2B',
                'muted'      => '#97607B',
                'accent'     => '#F472B6',
                'ornament'   => 'lotus',
            ],
            'indigo-night' => [
                'label'      => 'Indigo Night',
                'primary'    => '#312E81',
                'secondary'  => '#FCD34D',
                'background' => '#F5F5FF',
                'surface'    => '#FFFFFF',
                'text'       => '#1E1B4B',
                'muted'      => '#635F9E',
                'accent'     => '#6366F1',
                'ornament'   => 'star',
            ],
            'copper-cream' => [
                'label'      => 'Copper & Cream',
                'primary'    => '#B45309',
                'secondary'  => '#FDE68A',
                'background' => '#FFFBEB',
                'surface'    => '#FFFFFF',
                'text'       => '#3F2A10',
                'muted'      => '#8A7148',
                'accent'     => '#D97706',
                'ornament'   => 'arch',
            ],
            'mint-fresh' => [
                'label'      => 'Mint Fresh',
                'primary'    => '#0E7490',
                'secondary'  => '#A7F3D0',
                'background' => '#F0FDFA',
                'surface'    => '#FFFFFF',
                'text'       => '#123B44',
                'muted'      => '#5A8087',
                'accent'     => '#22D3EE',
                'ornament'   => 'line',
            ],
        ];
    }

    public static function get(string $slug): array
    {
        return self::all()[$slug] ?? self::all()['kumkum-red'];
    }

    /** @return array<int,string> */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    /** Font pairings, chosen so headings and body text look intentional. */
    public static function fontPairs(): array
    {
        return [
            'script-sans'  => ['heading' => 'GreatVibes', 'body' => 'NotoSans'],
            'serif-sans'   => ['heading' => 'PlayfairDisplay', 'body' => 'NotoSans'],
            'garamond-sans' => ['heading' => 'CormorantGaramond', 'body' => 'NotoSans'],
            'gujarati'     => ['heading' => 'NotoSansGujarati', 'body' => 'NotoSansGujarati'],
            'devanagari'   => ['heading' => 'NotoSansDevanagari', 'body' => 'NotoSansDevanagari'],
        ];
    }

    /**
     * Build the theme JSON stored on a template row.
     *
     * @return array<string,mixed>
     */
    public static function themeFor(
        string $paletteSlug,
        string $fontPair = 'script-sans',
        string $motion = 'gentle',
        ?string $ornament = null,
        ?string $stylePack = null
    ): array {
        $palette = self::get($paletteSlug);
        $fonts = self::fontPairs()[$fontPair] ?? self::fontPairs()['script-sans'];
        $style = self::stylePack($stylePack ?? 'kankotri-classic');

        return [
            'palette'       => $paletteSlug,
            'primary'       => $palette['primary'],
            'secondary'     => $palette['secondary'],
            'background'    => $palette['background'],
            'surface'       => $palette['surface'],
            'text'          => $palette['text'],
            'muted'         => $palette['muted'],
            'accent'        => $palette['accent'],
            'ornament'      => $ornament ?? $style['ornament'] ?? $palette['ornament'],
            'motion'        => $motion,
            'heading_font'  => $fonts['heading'],
            'body_font'     => $fonts['body'],
            'heading_scale' => $style['heading_scale'],
            'radius'        => $style['radius'],
            'border'        => 'rgba(0,0,0,.12)',
            // The structural axes. Without these, two templates on one layout
            // differ only in colour, which reads as the same card.
            'style'         => $stylePack ?? 'kankotri-classic',
            'frame'         => $style['frame'],
            'pattern'       => $style['pattern'],
            'divider'       => $style['divider'],
            'panel'         => $style['panel'],
            'counter'       => $style['counter'],
            'header'        => $style['header'],
        ];
    }

    /**
     * Style packs: a coherent set of the structural axes.
     *
     * Each is a whole look - the border, the paper, the rules, the panels, the
     * countdown and the opening - rather than a single setting, because those
     * choices only work together. A palette decides the colour; a pack decides
     * the shape; the layout decides the order of the content. Three
     * independent axes is what makes a large catalogue that does not repeat.
     *
     * @return array<string,array<string,string>>
     */
    public static function stylePacks(): array
    {
        return [
            'kankotri-classic' => [
                'label' => 'Classic Kankotri', 'frame' => 'double', 'pattern' => 'rice',
                'divider' => 'paisley', 'panel' => 'card', 'counter' => 'boxes',
                'header' => 'centered', 'radius' => '4px', 'heading_scale' => '1',
                'ornament' => 'paisley',
            ],
            'temple-torana' => [
                'label' => 'Temple Torana', 'frame' => 'torana', 'pattern' => 'temple',
                'divider' => 'leafline', 'panel' => 'tinted', 'counter' => 'tablet',
                'header' => 'centered', 'radius' => '10px', 'heading_scale' => '1.05',
                'ornament' => 'kalash',
            ],
            'royal-arch' => [
                'label' => 'Royal Arch', 'frame' => 'arch', 'pattern' => 'mandala',
                'divider' => 'knot', 'panel' => 'bordered', 'counter' => 'circles',
                'header' => 'monogram', 'radius' => '14px', 'heading_scale' => '1.1',
                'ornament' => 'mandala',
            ],
            'bandhani-festive' => [
                'label' => 'Bandhani Festive', 'frame' => 'scallop', 'pattern' => 'bandhani',
                'divider' => 'dots', 'panel' => 'card', 'counter' => 'circles',
                'header' => 'banner', 'radius' => '0px', 'heading_scale' => '1',
                'ornament' => 'bandhani',
            ],
            'marigold-garland' => [
                'label' => 'Marigold Garland', 'frame' => 'beaded', 'pattern' => 'wash',
                'divider' => 'swag', 'panel' => 'tinted', 'counter' => 'boxes',
                'header' => 'ribbon', 'radius' => '20px', 'heading_scale' => '1',
                'ornament' => 'garland',
            ],
            'minimal-press' => [
                'label' => 'Letterpress Minimal', 'frame' => 'rule', 'pattern' => 'plain',
                'divider' => 'double', 'panel' => 'plain', 'counter' => 'inline',
                'header' => 'stacked', 'radius' => '0px', 'heading_scale' => '.95',
                'ornament' => 'line',
            ],
            'modern-editorial' => [
                'label' => 'Modern Editorial', 'frame' => 'plain', 'pattern' => 'plain',
                'divider' => 'chevron', 'panel' => 'timeline', 'counter' => 'inline',
                'header' => 'stacked', 'radius' => '2px', 'heading_scale' => '1.15',
                'ornament' => 'star',
            ],
            'blockprint-folk' => [
                'label' => 'Block Print Folk', 'frame' => 'corners', 'pattern' => 'blockprint',
                'divider' => 'chevron', 'panel' => 'bordered', 'counter' => 'boxes',
                'header' => 'centered', 'radius' => '2px', 'heading_scale' => '1',
                'ornament' => 'swastik',
            ],
            'diya-evening' => [
                'label' => 'Diya Evening', 'frame' => 'ribbon', 'pattern' => 'wash',
                'divider' => 'dots', 'panel' => 'tinted', 'counter' => 'tablet',
                'header' => 'banner', 'radius' => '16px', 'heading_scale' => '1.05',
                'ornament' => 'diya',
            ],
            'peacock-scroll' => [
                'label' => 'Peacock Scroll', 'frame' => 'beaded', 'pattern' => 'paisley',
                'divider' => 'paisley', 'panel' => 'timeline', 'counter' => 'circles',
                'header' => 'centered', 'radius' => '22px', 'heading_scale' => '1.05',
                'ornament' => 'peacock',
            ],
            'vaishnav-flute' => [
                'label' => 'Vaishnav Flute', 'frame' => 'rule', 'pattern' => 'chevron',
                'divider' => 'leafline', 'panel' => 'card', 'counter' => 'inline',
                'header' => 'monogram', 'radius' => '8px', 'heading_scale' => '1',
                'ornament' => 'flute',
            ],
            'ganesh-mangal' => [
                'label' => 'Ganesh Mangal', 'frame' => 'double', 'pattern' => 'mandala',
                'divider' => 'knot', 'panel' => 'tinted', 'counter' => 'boxes',
                'header' => 'centered', 'radius' => '6px', 'heading_scale' => '1.08',
                'ornament' => 'ganesh',
            ],
            'shankh-vedic' => [
                'label' => 'Shankh Vedic', 'frame' => 'corners', 'pattern' => 'rice',
                'divider' => 'swag', 'panel' => 'bordered', 'counter' => 'tablet',
                'header' => 'centered', 'radius' => '3px', 'heading_scale' => '1',
                'ornament' => 'shankh',
            ],
            'lotus-serene' => [
                'label' => 'Lotus Serene', 'frame' => 'scallop', 'pattern' => 'plain',
                'divider' => 'leafline', 'panel' => 'plain', 'counter' => 'circles',
                'header' => 'ribbon', 'radius' => '0px', 'heading_scale' => '1',
                'ornament' => 'lotus',
            ],
            'om-sanskar' => [
                'label' => 'Om Sanskar', 'frame' => 'torana', 'pattern' => 'blockprint',
                'divider' => 'double', 'panel' => 'timeline', 'counter' => 'boxes',
                'header' => 'stacked', 'radius' => '12px', 'heading_scale' => '1',
                'ornament' => 'om',
            ],
            'celebration-pop' => [
                'label' => 'Celebration Pop', 'frame' => 'ribbon', 'pattern' => 'chevron',
                'divider' => 'dots', 'panel' => 'card', 'counter' => 'circles',
                'header' => 'banner', 'radius' => '24px', 'heading_scale' => '1.1',
                'ornament' => 'star',
            ],
        ];
    }

    /** @return array<string,string> */
    public static function stylePack(string $slug): array
    {
        $packs = self::stylePacks();

        return $packs[$slug] ?? $packs['kankotri-classic'];
    }

    /** @return array<int,string> */
    public static function stylePackSlugs(): array
    {
        return array_keys(self::stylePacks());
    }

    /**
     * Which style pack suits a curated template?
     *
     * Occasion first - a Ganesh card wants the Ganesh pack - and then the
     * number in the code rotates through that occasion's packs, so sibling
     * templates (KANK-001 … KANK-005) never come out looking the same. A code
     * with no mapping is spread evenly by its own hash rather than defaulting,
     * which would pile everything onto one look again.
     */
    public static function packForCode(string $code): string
    {
        return self::packForTemplate($code, 0);
    }

    /**
     * Which style pack suits this template, at this position on its layout?
     *
     * @param int $ordinal how many templates already sit on the same layout
     */
    public static function packForTemplate(string $code, int $ordinal): string
    {
        $preferred = self::packsForOccasion($code);

        return $preferred[abs($ordinal) % count($preferred)];
    }

    /**
     * Pick a pack for this template, avoiding the ones its layout already uses.
     *
     * Rotation alone was not enough: twelve templates share the mandir layout,
     * and several occasions name the same first choice, so four came out
     * identical in shape - the very complaint this whole change answers.
     * Taking the first unused option means no two cards on a layout repeat a
     * look while packs remain: sixteen packs against twelve templates, so on
     * this catalogue none repeat.
     *
     * @param array<int,string> $takenOnLayout packs already used on that layout
     */
    public static function choosePack(string $code, array $takenOnLayout): string
    {
        foreach (self::packsForOccasion($code) as $pack) {
            if (!in_array($pack, $takenOnLayout, true)) {
                return $pack;
            }
        }

        // The occasion's own packs are all spoken for: take any unused pack,
        // starting from a point that depends on the code so the leftovers do
        // not all land on the same one.
        $all = self::stylePackSlugs();
        $from = abs((int) crc32($code)) % count($all);
        for ($step = 0; $step < count($all); $step++) {
            $pack = $all[($from + $step) % count($all)];
            if (!in_array($pack, $takenOnLayout, true)) {
                return $pack;
            }
        }

        return self::packsForOccasion($code)[0];
    }

    /**
     * The packs that suit this template's occasion, best first.
     *
     * A Ganesh card should not land in the block-print folk pack by accident,
     * so the occasion decides the shortlist and the caller decides which of
     * those is still free.
     *
     * @return array<int,string>
     */
    public static function packsForOccasion(string $code): array
    {
        /** @var array<string,array<int,string>> occasion prefix => packs, best first */
        $byPrefix = [
            'KANK' => ['kankotri-classic', 'bandhani-festive', 'blockprint-folk', 'royal-arch', 'shankh-vedic'],
            'KRSN' => ['vaishnav-flute', 'peacock-scroll', 'temple-torana'],
            'GNSH' => ['ganesh-mangal', 'blockprint-folk', 'temple-torana'],
            'MHDV' => ['om-sanskar', 'royal-arch', 'shankh-vedic'],
            'RAM'  => ['shankh-vedic', 'ganesh-mangal', 'temple-torana'],
            'SWMN' => ['om-sanskar', 'lotus-serene', 'temple-torana'],
            'JAIN' => ['lotus-serene', 'minimal-press', 'om-sanskar'],
            'HNDU' => ['ganesh-mangal', 'shankh-vedic', 'kankotri-classic'],
            'POOJ' => ['shankh-vedic', 'temple-torana', 'om-sanskar', 'ganesh-mangal', 'lotus-serene'],
            'GRIH' => ['temple-torana', 'kankotri-classic', 'diya-evening'],
            'ROYL' => ['royal-arch', 'marigold-garland', 'peacock-scroll'],
            'MDRN' => ['modern-editorial', 'minimal-press', 'celebration-pop'],
            'MNML' => ['minimal-press', 'modern-editorial'],
            'FLRL' => ['lotus-serene', 'peacock-scroll', 'marigold-garland'],
            'MHND' => ['bandhani-festive', 'marigold-garland'],
            'HALD' => ['marigold-garland', 'bandhani-festive'],
            'SNGT' => ['celebration-pop', 'diya-evening'],
            'GRBA' => ['bandhani-festive', 'celebration-pop'],
            'RCPT' => ['modern-editorial', 'diya-evening'],
            'EVNT' => ['celebration-pop', 'modern-editorial', 'diya-evening', 'marigold-garland', 'minimal-press'],
            'BDAY' => ['celebration-pop', 'diya-evening'],
            'BABY' => ['lotus-serene', 'celebration-pop'],
            'MUND' => ['om-sanskar', 'shankh-vedic'],
            'THRD' => ['shankh-vedic', 'om-sanskar'],
            'BUSN' => ['minimal-press', 'modern-editorial', 'blockprint-folk', 'diya-evening', 'celebration-pop'],
        ];

        $prefix = strtoupper(explode('-', $code . '-')[0]);

        return $byPrefix[$prefix] ?? self::stylePackSlugs();
    }
}
