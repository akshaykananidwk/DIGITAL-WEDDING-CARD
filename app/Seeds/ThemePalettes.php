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
        ?string $ornament = null
    ): array {
        $palette = self::get($paletteSlug);
        $fonts = self::fontPairs()[$fontPair] ?? self::fontPairs()['script-sans'];

        return [
            'palette'       => $paletteSlug,
            'primary'       => $palette['primary'],
            'secondary'     => $palette['secondary'],
            'background'    => $palette['background'],
            'surface'       => $palette['surface'],
            'text'          => $palette['text'],
            'muted'         => $palette['muted'],
            'accent'        => $palette['accent'],
            'ornament'      => $ornament ?? $palette['ornament'],
            'motion'        => $motion,
            'heading_font'  => $fonts['heading'],
            'body_font'     => $fonts['body'],
            'heading_scale' => '1',
            'radius'        => '18px',
            'border'        => 'rgba(0,0,0,.12)',
        ];
    }
}
