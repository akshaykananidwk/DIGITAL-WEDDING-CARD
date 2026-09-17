<?php

declare(strict_types=1);

namespace App\Seeds;

/**
 * The bundled fonts.
 *
 * All five ship with the application under the SIL Open Font License, which
 * is why Gujarati and Hindi work in both the browser and the PDF without the
 * administrator having to find and upload a font first.
 */
final class FontSeeder extends Seeder
{
    /** slug => [name, family, file, script, category, pdf capable, default] */
    private const FONTS = [
        'noto-sans' => [
            'name' => 'Noto Sans', 'family' => 'NotoSans',
            'file' => 'assets/fonts/NotoSans-Regular.ttf',
            'script' => 'latin', 'category' => 'sans', 'pdf' => true, 'default' => true,
            'preview' => 'The quick brown fox jumps over the lazy dog',
        ],
        'noto-sans-gujarati' => [
            'name' => 'Noto Sans Gujarati', 'family' => 'NotoSansGujarati',
            'file' => 'assets/fonts/NotoSansGujarati-Regular.ttf',
            'script' => 'gujarati', 'category' => 'sans', 'pdf' => true, 'default' => true,
            'preview' => 'શુભ લગ્ન પ્રસંગ આમંત્રણ',
        ],
        'noto-sans-gujarati-bold' => [
            'name' => 'Noto Sans Gujarati Bold', 'family' => 'NotoSansGujarati',
            'file' => 'assets/fonts/NotoSansGujarati-Bold.ttf',
            'script' => 'gujarati', 'category' => 'sans', 'pdf' => true, 'default' => false,
            'weight' => '700',
            'preview' => 'શુભ લગ્ન પ્રસંગ આમંત્રણ',
        ],
        'noto-sans-devanagari' => [
            'name' => 'Noto Sans Devanagari', 'family' => 'NotoSansDevanagari',
            'file' => 'assets/fonts/NotoSansDevanagari-Regular.ttf',
            'script' => 'devanagari', 'category' => 'sans', 'pdf' => true, 'default' => true,
            'preview' => 'शुभ विवाह निमंत्रण',
        ],
        'noto-sans-devanagari-bold' => [
            'name' => 'Noto Sans Devanagari Bold', 'family' => 'NotoSansDevanagari',
            'file' => 'assets/fonts/NotoSansDevanagari-Bold.ttf',
            'script' => 'devanagari', 'category' => 'sans', 'pdf' => true, 'default' => false,
            'weight' => '700',
            'preview' => 'शुभ विवाह निमंत्रण',
        ],
        'great-vibes' => [
            'name' => 'Great Vibes', 'family' => 'GreatVibes',
            'file' => 'assets/fonts/GreatVibes-Regular.ttf',
            'script' => 'latin', 'category' => 'script', 'pdf' => true, 'default' => false,
            'preview' => 'Rahul weds Priya',
        ],
        'playfair-display' => [
            'name' => 'Playfair Display', 'family' => 'PlayfairDisplay',
            'file' => 'assets/fonts/PlayfairDisplay-Regular.ttf',
            'script' => 'latin', 'category' => 'serif', 'pdf' => true, 'default' => false,
            'preview' => 'Save the date',
        ],
        'cormorant-garamond' => [
            'name' => 'Cormorant Garamond', 'family' => 'CormorantGaramond',
            'file' => 'assets/fonts/CormorantGaramond-Regular.ttf',
            'script' => 'latin', 'category' => 'serif', 'pdf' => true, 'default' => false,
            'preview' => 'With the blessings of our elders',
        ],
    ];

    public function run(): void
    {
        $added = 0;
        $order = 0;
        foreach (self::FONTS as $slug => $font) {
            $order += 10;

            $exists = (int) $this->db->value(
                'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('fonts')) . ' WHERE slug = :slug',
                ['slug' => $slug],
                0
            ) > 0;
            if ($exists) {
                continue;
            }

            // Only register a font that is actually on disk.
            $absolute = ROOT_PATH . '/' . $font['file'];
            if (!is_file($absolute)) {
                $this->note('Skipped ' . $font['name'] . ': file not found.');
                continue;
            }

            $this->db->insert('fonts', [
                'name'         => $font['name'],
                'slug'         => $slug,
                'family'       => $font['family'],
                'source'       => 'bundled',
                'file_path'    => $font['file'],
                'weight'       => $font['weight'] ?? '400',
                'style'        => 'normal',
                'script'       => $font['script'],
                'category'     => $font['category'],
                'preview_text' => $font['preview'],
                'pdf_capable'  => $font['pdf'] ? 1 : 0,
                'is_active'    => 1,
                'is_default'   => $font['default'] ? 1 : 0,
                'sort_order'   => $order,
                'created_at'   => $this->now(),
                'updated_at'   => $this->now(),
            ]);
            $added++;
        }
        $this->note($added . ' font(s) registered.');
    }
}
