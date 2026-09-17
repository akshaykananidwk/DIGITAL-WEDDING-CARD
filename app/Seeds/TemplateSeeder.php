<?php

declare(strict_types=1);

namespace App\Seeds;

use App\Core\Str;
use App\Repositories\CategoryRepository;

/**
 * The starter template catalogue.
 *
 * Every entry is a row, not a file: a layout renderer, a palette, a font
 * pairing, a field preset and the taxonomy it belongs to. The variant
 * generator then multiplies these across palettes and layouts, which is how
 * the catalogue reaches thousands of designs.
 */
final class TemplateSeeder extends Seeder
{
    /**
     * Curated templates.
     *
     * [code, name, subcategory slug, layout, palette, font pair, preset,
     *  type, motion, tags, featured]
     */
    private const CURATED = [
        // ---- Gujarati / traditional kankotri ----
        ['KANK-001', 'Classic Gujarati Kankotri', 'traditional-kankotri', 'classic-kankotri', 'kumkum-red', 'gujarati', 'wedding', 'kankotri', 'gentle', ['kankotri', 'gujarati', 'traditional', 'red'], true],
        ['KANK-002', 'Kumkum Kankotri', 'gujarati-wedding', 'classic-kankotri', 'maroon-gold', 'gujarati', 'wedding', 'kankotri', 'gentle', ['kankotri', 'gujarati', 'maroon', 'gold'], true],
        ['KANK-003', 'Sandalwood Kankotri', 'traditional-wedding', 'classic-kankotri', 'sandalwood', 'garamond-sans', 'wedding', 'kankotri', 'gentle', ['kankotri', 'traditional', 'beige'], false],
        ['KANK-004', 'Digital Kankotri Deluxe', 'digital-kankotri', 'multi-page-book', 'kumkum-red', 'gujarati', 'wedding', 'multi_page', 'rich', ['kankotri', 'digital', 'multipage'], true],
        ['KANK-005', '3D Envelope Kankotri', '3d-kankotri', 'envelope-3d', 'maroon-gold', 'script-sans', 'wedding', 'three_d', 'rich', ['3d', 'envelope', 'animated'], true],

        // ---- Deity themes ----
        ['KRSN-001', 'Krishna Flute', 'krishna-theme', 'krishna-scroll', 'krishna-blue', 'script-sans', 'wedding', 'animated', 'rich', ['krishna', 'flute', 'peacock', 'blue'], true],
        ['KRSN-002', 'Radha Krishna Raas', 'radha-krishna-theme', 'krishna-scroll', 'lotus-pink', 'garamond-sans', 'wedding', 'animated', 'gentle', ['radha', 'krishna', 'pink'], false],
        ['KRSN-003', 'Dwarkadhish Darshan', 'dwarkadhish-theme', 'temple-mandala', 'krishna-blue', 'gujarati', 'wedding', 'kankotri', 'gentle', ['dwarkadhish', 'dwarka', 'krishna'], true],
        ['GNSH-001', 'Shubh Ganesh', 'ganesh-theme', 'temple-mandala', 'saffron-white', 'devanagari', 'wedding', 'kankotri', 'gentle', ['ganesh', 'ganpati', 'saffron'], true],
        ['MHDV-001', 'Mahadev Trishul', 'mahadev-theme', 'temple-mandala', 'midnight-gold', 'devanagari', 'wedding', 'animated', 'rich', ['mahadev', 'shiv', 'dark'], false],
        ['RAM-001', 'Shri Ram Ayodhya', 'ram-theme', 'temple-mandala', 'saffron-white', 'devanagari', 'wedding', 'kankotri', 'gentle', ['ram', 'ayodhya', 'saffron'], false],
        ['SWMN-001', 'Swaminarayan Blessings', 'swaminarayan-theme', 'temple-mandala', 'copper-cream', 'gujarati', 'wedding', 'kankotri', 'gentle', ['swaminarayan', 'akshardham'], false],

        // ---- Style-led weddings ----
        ['ROYL-001', 'Royal Maharaja Arch', 'royal-wedding', 'royal-arch', 'royal-purple', 'garamond-sans', 'wedding', 'static', 'gentle', ['royal', 'regal', 'purple', 'gold'], true],
        ['ROYL-002', 'Midnight Royal', 'royal-wedding', 'royal-arch', 'midnight-gold', 'serif-sans', 'wedding', 'animated', 'rich', ['royal', 'dark', 'gold', 'luxury'], true],
        ['FLRL-001', 'Marigold Garland', 'floral-wedding', 'floral-minimal', 'marigold', 'script-sans', 'wedding', 'static', 'gentle', ['floral', 'marigold', 'orange'], true],
        ['FLRL-002', 'Rose Blush Botanical', 'floral-wedding', 'floral-minimal', 'rose-blush', 'garamond-sans', 'wedding', 'static', 'gentle', ['floral', 'rose', 'pink', 'soft'], false],
        ['MNML-001', 'Ivory Minimal', 'minimal-wedding', 'floral-minimal', 'ivory-minimal', 'serif-sans', 'wedding', 'static', 'none', ['minimal', 'ivory', 'clean', 'elegant'], true],
        ['MDRN-001', 'Modern Hero', 'modern-wedding', 'modern-hero', 'peacock-teal', 'serif-sans', 'wedding', 'static', 'gentle', ['modern', 'photo', 'teal'], true],
        ['MDRN-002', 'Emerald Modern', 'modern-wedding', 'modern-hero', 'emerald-jade', 'serif-sans', 'wedding', 'static', 'gentle', ['modern', 'green', 'clean'], false],
        ['ANIM-001', 'Animated Petals', 'animated-wedding', 'floral-minimal', 'lotus-pink', 'script-sans', 'wedding', 'animated', 'rich', ['animated', 'petals', 'motion'], true],
        ['HNDU-001', 'Vedic Mandap', 'hindu-wedding', 'royal-arch', 'kumkum-red', 'devanagari', 'wedding', 'kankotri', 'gentle', ['hindu', 'vedic', 'mandap'], false],
        ['JAIN-001', 'Jain Serenity', 'jain-wedding', 'floral-minimal', 'ivory-minimal', 'gujarati', 'wedding', 'static', 'none', ['jain', 'minimal', 'peaceful'], false],

        // ---- Wedding functions ----
        ['ENGG-001', 'Ring Ceremony', 'engagement', 'floral-minimal', 'rose-blush', 'script-sans', 'engagement', 'static', 'gentle', ['engagement', 'ring', 'sagai'], true],
        ['RCPT-001', 'Evening Reception', 'reception', 'modern-hero', 'midnight-gold', 'serif-sans', 'reception', 'static', 'gentle', ['reception', 'evening', 'elegant'], true],
        ['HALD-001', 'Haldi Sunshine', 'haldi', 'celebration-pop', 'marigold', 'script-sans', 'wedding', 'animated', 'rich', ['haldi', 'yellow', 'fun'], false],
        ['MHND-001', 'Mehndi Henna', 'mehndi', 'celebration-pop', 'emerald-jade', 'script-sans', 'wedding', 'animated', 'rich', ['mehndi', 'henna', 'green'], true],
        ['SNGT-001', 'Sangeet Night', 'sangeet', 'celebration-pop', 'indigo-night', 'serif-sans', 'wedding', 'animated', 'rich', ['sangeet', 'music', 'night'], false],
        ['GRBA-001', 'Garba Dandiya Nights', 'garba', 'celebration-pop', 'marigold', 'gujarati', 'garba', 'animated', 'rich', ['garba', 'navratri', 'dandiya'], true],

        // ---- Religious ----
        ['POOJ-001', 'Satyanarayan Katha', 'satyanarayan-katha', 'temple-mandala', 'saffron-white', 'devanagari', 'pooja', 'kankotri', 'gentle', ['katha', 'satyanarayan', 'pooja'], true],
        ['POOJ-002', 'Ganesh Puja Invitation', 'ganesh-puja', 'temple-mandala', 'marigold', 'devanagari', 'pooja', 'static', 'gentle', ['ganesh', 'pooja'], false],
        ['POOJ-003', 'Bhagwat Katha Saptah', 'bhagwat-katha', 'krishna-scroll', 'krishna-blue', 'devanagari', 'pooja', 'kankotri', 'gentle', ['bhagwat', 'katha', 'krishna'], false],
        ['POOJ-004', 'Mataji Mandir Function', 'mataji-function', 'temple-mandala', 'kumkum-red', 'gujarati', 'pooja', 'static', 'gentle', ['mataji', 'devi', 'mandir'], false],
        ['POOJ-005', 'Pran Pratishtha', 'pran-pratishtha', 'temple-mandala', 'copper-cream', 'devanagari', 'pooja', 'kankotri', 'gentle', ['pratishtha', 'temple'], false],

        // ---- Business ----
        ['BUSN-001', 'Grand Opening Muhurat', 'shop-opening', 'business-launch', 'saffron-white', 'serif-sans', 'business', 'static', 'gentle', ['shop', 'opening', 'muhurat'], true],
        ['BUSN-002', 'Showroom Launch', 'showroom-opening', 'business-launch', 'peacock-teal', 'serif-sans', 'business', 'static', 'gentle', ['showroom', 'retail', 'launch'], false],
        ['BUSN-003', 'Restaurant Opening', 'restaurant-opening', 'business-launch', 'copper-cream', 'serif-sans', 'business', 'static', 'gentle', ['restaurant', 'food', 'opening'], false],
        ['BUSN-004', 'Corporate Office Opening', 'office-opening', 'modern-hero', 'indigo-night', 'serif-sans', 'business', 'static', 'none', ['office', 'corporate'], false],
        ['BUSN-005', 'Product Launch Modern', 'product-launch', 'modern-hero', 'mint-fresh', 'serif-sans', 'business', 'static', 'gentle', ['product', 'launch', 'modern'], false],

        // ---- Personal ----
        ['BDAY-001', 'Birthday Confetti', 'birthday', 'celebration-pop', 'lotus-pink', 'script-sans', 'birthday', 'animated', 'rich', ['birthday', 'confetti', 'fun'], true],
        ['BDAY-002', 'First Birthday', 'birthday', 'celebration-pop', 'mint-fresh', 'script-sans', 'birthday', 'animated', 'gentle', ['birthday', 'baby', 'pastel'], false],
        ['ANNV-001', 'Silver Anniversary', 'anniversary', 'royal-arch', 'ivory-minimal', 'garamond-sans', 'anniversary', 'static', 'gentle', ['anniversary', 'silver', 'elegant'], true],
        ['BABY-001', 'Naming Ceremony', 'naming-ceremony', 'floral-minimal', 'mint-fresh', 'script-sans', 'baby', 'static', 'gentle', ['naming', 'namkaran', 'baby'], true],
        ['BABY-002', 'Baby Shower Blossom', 'baby-shower', 'floral-minimal', 'rose-blush', 'script-sans', 'baby', 'static', 'gentle', ['baby', 'shower', 'godh bharai'], false],
        ['MUND-001', 'Mundan Ceremony', 'mundan', 'temple-mandala', 'saffron-white', 'devanagari', 'baby', 'static', 'gentle', ['mundan', 'ceremony'], false],
        ['GRIH-001', 'Griha Pravesh Vastu', 'griha-pravesh', 'temple-mandala', 'copper-cream', 'gujarati', 'housewarming', 'kankotri', 'gentle', ['griha pravesh', 'vastu', 'housewarming'], true],
        ['THRD-001', 'Janoi Ceremony', 'thread-ceremony', 'temple-mandala', 'saffron-white', 'devanagari', 'baby', 'static', 'gentle', ['janoi', 'yagnopavit'], false],

        // ---- Events ----
        ['EVNT-001', 'Cultural Evening', 'cultural-event', 'modern-hero', 'indigo-night', 'serif-sans', 'event', 'static', 'gentle', ['cultural', 'program'], false],
        ['EVNT-002', 'Navratri Mahotsav', 'navratri', 'celebration-pop', 'marigold', 'gujarati', 'garba', 'animated', 'rich', ['navratri', 'garba', 'festival'], true],
        ['EVNT-003', 'Seminar Invitation', 'seminar', 'modern-hero', 'mint-fresh', 'serif-sans', 'event', 'static', 'none', ['seminar', 'professional'], false],
        ['EVNT-004', 'Exhibition Opening', 'exhibition', 'modern-hero', 'peacock-teal', 'serif-sans', 'event', 'static', 'none', ['exhibition', 'expo'], false],
        ['EVNT-005', 'Festival Celebration', 'festival', 'celebration-pop', 'kumkum-red', 'devanagari', 'event', 'animated', 'rich', ['festival', 'diwali'], false],
    ];

    public function run(): void
    {
        $subcategories = $this->db->pairs(
            'SELECT slug, id FROM ' . $this->db->wrap($this->db->table('subcategories'))
        );
        $subcategoryCategories = $this->db->pairs(
            'SELECT id, category_id FROM ' . $this->db->wrap($this->db->table('subcategories'))
        );

        $added = 0;
        $order = 0;

        foreach (self::CURATED as [$code, $name, $subSlug, $layout, $palette, $fontPair, $preset, $type, $motion, $tags, $featured]) {
            $order += 10;

            $exists = (int) $this->db->value(
                'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('templates')) . ' WHERE code = :code',
                ['code' => $code],
                0
            ) > 0;
            if ($exists) {
                continue;
            }

            $subcategoryId = isset($subcategories[$subSlug]) ? (int) $subcategories[$subSlug] : null;
            $categoryId = $subcategoryId !== null
                ? (int) ($subcategoryCategories[$subcategoryId] ?? 0)
                : 0;
            if ($categoryId === 0) {
                // A curated entry whose taxonomy is missing is skipped rather
                // than attached to the wrong category.
                continue;
            }

            $templateId = $this->insertTemplate([
                'code'        => $code,
                'name'        => $name,
                'layout'      => $layout,
                'palette'     => $palette,
                'font_pair'   => $fontPair,
                'preset'      => $preset,
                'type'        => $type,
                'motion'      => $motion,
                'tags'        => $tags,
                'featured'    => $featured,
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
                'sort_order'  => $order,
                'language'    => in_array($fontPair, ['gujarati'], true) ? 'gu'
                    : (in_array($fontPair, ['devanagari'], true) ? 'hi' : 'multi'),
            ]);

            $this->insertFields($templateId, $preset);
            $added++;
        }

        (new CategoryRepository())->refreshCounts();
        $this->note($added . ' curated template(s) added.');
    }

    /**
     * Insert one template row.
     *
     * @param array<string,mixed> $spec
     */
    public function insertTemplate(array $spec): int
    {
        $palette = ThemePalettes::get((string) $spec['palette']);
        $fonts = ThemePalettes::fontPairs()[(string) $spec['font_pair']]
            ?? ThemePalettes::fontPairs()['script-sans'];
        $theme = ThemePalettes::themeFor(
            (string) $spec['palette'],
            (string) $spec['font_pair'],
            (string) $spec['motion']
        );

        $name = (string) $spec['name'];
        $tags = (array) $spec['tags'];
        $type = (string) $spec['type'];

        $description = $name . ' - a ready-to-use ' . str_replace('_', ' ', $type)
            . ' invitation design in the ' . strtolower((string) $palette['label']) . ' palette. '
            . 'Add your names, dates, photos and message, then share the link on WhatsApp.';

        $slug = $this->uniqueSlug(Str::slug($name . ' ' . strtolower((string) $spec['code'])));

        return $this->db->insert('templates', [
            'code'            => (string) $spec['code'],
            'name'            => $name,
            'slug'            => $slug,
            'description'     => $description,
            'category_id'     => (int) $spec['category_id'],
            'subcategory_id'  => $spec['subcategory_id'],
            'type'            => $type,
            'layout_key'      => (string) $spec['layout'],
            'language'        => (string) ($spec['language'] ?? 'multi'),
            'orientation'     => 'portrait',
            'canvas_width'    => 1080,
            'canvas_height'   => 1920,
            'page_count'      => (string) $spec['layout'] === 'multi-page-book' ? 4 : 1,
            'theme'           => json_encode($theme, JSON_UNESCAPED_UNICODE),
            'thumbnail'       => null,
            'preview_images'  => json_encode([], JSON_UNESCAPED_UNICODE),
            'demo_data'       => json_encode(FieldPresets::demoData((string) $spec['preset']), JSON_UNESCAPED_UNICODE),
            'tags'            => json_encode($tags, JSON_UNESCAPED_UNICODE),
            'search_keywords' => mb_substr(implode(' ', array_merge(
                [$name, (string) $palette['label'], (string) $spec['layout'], $type],
                $tags
            )), 0, 500),
            'color_primary'   => (string) $palette['primary'],
            'color_secondary' => (string) $palette['secondary'],
            'color_background' => (string) $palette['background'],
            'font_heading'    => (string) $fonts['heading'],
            'font_body'       => (string) $fonts['body'],
            'supports_music'     => 1,
            'supports_gallery'   => 1,
            'supports_countdown' => 1,
            'supports_rsvp'      => 1,
            'supports_map'       => 1,
            'has_animation'   => in_array($type, ['animated', 'three_d'], true) ? 1 : 0,
            'is_premium'      => 0,
            'is_active'       => 1,
            'is_featured'     => !empty($spec['featured']) ? 1 : 0,
            'sort_order'      => (int) ($spec['sort_order'] ?? 100),
            'meta_title'      => $name . ' invitation card template',
            'meta_description' => mb_substr($description, 0, 300),
            'created_at'      => $this->now(),
            'updated_at'      => $this->now(),
        ]);
    }

    /** Create the field rows for a template from a preset. */
    public function insertFields(int $templateId, string $preset): int
    {
        $rows = [];
        $order = 0;
        foreach (FieldPresets::get($preset) as $field) {
            $order += 10;
            $rows[] = [
                'template_id'       => $templateId,
                'field_key'         => (string) $field['field_key'],
                'label'             => (string) $field['label'],
                'label_gu'          => (string) $field['label_gu'],
                'label_hi'          => (string) $field['label_hi'],
                'type'              => (string) $field['type'],
                'section'           => (string) $field['section'],
                'placeholder'       => (string) $field['placeholder'],
                'help_text'         => (string) $field['help_text'],
                'default_value'     => null,
                'options'           => null,
                'validation'        => null,
                'max_length'        => $field['max_length'],
                'is_required'       => !empty($field['is_required']) ? 1 : 0,
                'is_editable'       => 1,
                'is_visible'        => 1,
                'is_ai_generatable' => !empty($field['is_ai_generatable']) ? 1 : 0,
                'sort_order'        => $order,
                'created_at'        => $this->now(),
                'updated_at'        => $this->now(),
            ];
        }
        if ($rows === []) {
            return 0;
        }
        $this->db->insertMany('template_fields', $rows);
        return count($rows);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base !== '' ? $base : 'template';
        $candidate = $slug;
        $i = 1;
        while ((int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('templates')) . ' WHERE slug = :slug',
            ['slug' => $candidate],
            0
        ) > 0) {
            $candidate = $slug . '-' . (++$i);
        }
        return $candidate;
    }

    public static function curatedCount(): int
    {
        return count(self::CURATED);
    }
}
