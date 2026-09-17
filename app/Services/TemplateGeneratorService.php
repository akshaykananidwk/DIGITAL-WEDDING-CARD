<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Repositories\CategoryRepository;
use App\Seeds\FieldPresets;
use App\Seeds\TemplateSeeder;
use App\Seeds\ThemePalettes;

/**
 * Template variant generator.
 *
 * This is the practical proof that the template engine is data driven: a
 * layout renderer crossed with a palette, a font pairing, an ornament and a
 * subcategory produces a distinct, usable design, and the catalogue scales to
 * thousands of rows without a single new file.
 *
 * Used by the installer to seed a full starter catalogue, and by
 * Admin → Templates → Generate to build a large set for load testing.
 */
final class TemplateGeneratorService
{
    /** Layout renderer => the presets it suits. */
    private const LAYOUT_PRESETS = [
        'classic-kankotri' => ['wedding', 'engagement', 'pooja', 'housewarming'],
        'krishna-scroll'   => ['wedding', 'pooja'],
        'royal-arch'       => ['wedding', 'reception', 'anniversary'],
        'floral-minimal'   => ['wedding', 'engagement', 'baby', 'anniversary'],
        'envelope-3d'      => ['wedding', 'engagement'],
        'multi-page-book'  => ['wedding', 'reception'],
        'modern-hero'      => ['wedding', 'reception', 'business', 'event'],
        'temple-mandala'   => ['pooja', 'housewarming', 'wedding'],
        'celebration-pop'  => ['birthday', 'garba', 'event', 'baby'],
        'business-launch'  => ['business'],
    ];

    /** Adjectives that make generated names read like real product names. */
    private const STYLE_WORDS = [
        'Classic', 'Elegant', 'Regal', 'Serene', 'Grand', 'Divine', 'Vintage',
        'Blossom', 'Heritage', 'Radiant', 'Golden', 'Sacred', 'Graceful',
        'Timeless', 'Festive', 'Opulent', 'Delicate', 'Majestic',
    ];

    /** Every motif the ornament partial can draw. */
    private const ORNAMENTS = [
        'paisley', 'mandala', 'floral', 'peacock', 'arch', 'temple', 'lotus',
        'line', 'leaf', 'star', 'ganesh', 'kalash', 'diya', 'shankh', 'flute',
        'om', 'swastik', 'garland', 'torana', 'bandhani',
    ];

    /** @return array{created:int,skipped:int,message:string} */
    public function generate(int $count, bool $activate = true, ?int $userId = null): array
    {
        $count = max(1, min(20000, $count));
        $db = Database::instance();
        $seeder = new TemplateSeeder($db);

        $subcategories = $db->select(
            'SELECT s.id, s.slug, s.name, s.category_id, s.theme_tags
             FROM ' . $db->wrap($db->table('subcategories')) . ' s
             WHERE s.deleted_at IS NULL AND s.is_active = 1
             ORDER BY s.id ASC'
        );
        if ($subcategories === []) {
            return ['created' => 0, 'skipped' => 0, 'message' => 'Add categories first - there is nothing to attach templates to.'];
        }

        $palettes = ThemePalettes::slugs();
        $layouts = array_keys(self::LAYOUT_PRESETS);
        $fontPairs = array_keys(ThemePalettes::fontPairs());

        $nextIndex = $this->nextGeneratedIndex($db);
        $created = 0;
        $skipped = 0;

        // Batched so a 10,000 template run does not hold one giant transaction.
        $batchSize = 100;
        $pending = $count;

        while ($pending > 0) {
            $thisBatch = min($batchSize, $pending);
            $db->beginTransaction();
            try {
                for ($i = 0; $i < $thisBatch; $i++) {
                    $index = $nextIndex + $created + $skipped;
                    $spec = $this->buildSpec($index, $subcategories, $palettes, $layouts, $fontPairs, $activate);

                    $exists = (int) $db->value(
                        'SELECT COUNT(*) FROM ' . $db->wrap($db->table('templates')) . ' WHERE code = :code',
                        ['code' => $spec['code']],
                        0
                    ) > 0;
                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    $templateId = $seeder->insertTemplate($spec);
                    $seeder->insertFields($templateId, (string) $spec['preset']);
                    if ($userId !== null) {
                        $db->update('templates', ['created_by' => $userId], ['id' => $templateId]);
                    }
                    $created++;
                }
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                Logger::error('Template generation failed: ' . $e->getMessage());
                return [
                    'created' => $created,
                    'skipped' => $skipped,
                    'message' => 'Generation stopped after ' . $created . ' template(s): ' . $e->getMessage(),
                ];
            }
            $pending -= $thisBatch;
        }

        (new CategoryRepository())->refreshCounts();
        (new \App\Repositories\TemplateRepository())->flushCaches();

        AuditService::instance()->log(
            'templates.generate',
            'template',
            null,
            $created . ' template variants generated'
        );

        return [
            'created' => $created,
            'skipped' => $skipped,
            'message' => $created . ' template(s) generated'
                . ($skipped > 0 ? ', ' . $skipped . ' already existed' : '') . '.',
        ];
    }

    /**
     * Build a deterministic variant specification.
     *
     * Deterministic on purpose: regenerating produces the same catalogue, so
     * a demo environment and a production install look alike.
     *
     * @param array<int,array<string,mixed>> $subcategories
     * @param array<int,string> $palettes
     * @param array<int,string> $layouts
     * @param array<int,string> $fontPairs
     * @return array<string,mixed>
     */
    private function buildSpec(
        int $index,
        array $subcategories,
        array $palettes,
        array $layouts,
        array $fontPairs,
        bool $activate
    ): array {
        $subcategory = $subcategories[$index % count($subcategories)];
        $palette = $palettes[intdiv($index, count($subcategories)) % count($palettes)];
        $paletteTokens = ThemePalettes::get($palette);

        // Pick a layout that suits this subcategory's preset.
        $preset = $this->presetForSubcategory((string) $subcategory['slug']);
        $eligible = array_values(array_filter(
            $layouts,
            static fn (string $layout): bool => in_array($preset, self::LAYOUT_PRESETS[$layout], true)
        ));
        if ($eligible === []) {
            $eligible = $layouts;
        }
        $layout = $eligible[intdiv($index, 3) % count($eligible)];

        // Language-appropriate font pairing, with variety.
        $fontPair = $this->fontPairFor($preset, $index, $fontPairs);
        $language = match ($fontPair) {
            'gujarati'   => 'gu',
            'devanagari' => 'hi',
            default      => 'multi',
        };

        $style = self::STYLE_WORDS[$index % count(self::STYLE_WORDS)];
        $ornament = self::ORNAMENTS[$index % count(self::ORNAMENTS)];

        /*
         * The structural look. Stepped by a number coprime with the pack count
         * so it does not fall into lockstep with the palette (which steps every
         * few hundred) or the layout (every three): layout x palette x pack is
         * what keeps a thousand generated cards from repeating a look.
         */
        $packs = ThemePalettes::stylePackSlugs();
        $stylePack = $packs[($index * 7) % count($packs)];
        $motion = match ($index % 4) {
            0 => 'rich',
            1 => 'none',
            default => 'gentle',
        };
        $type = $this->typeFor($layout, $motion);

        $packLabel = (string) (ThemePalettes::stylePack($stylePack)['label'] ?? '');
        $name = $style . ' ' . $paletteTokens['label'] . ' ' . $subcategory['name'];

        $tags = array_values(array_unique(array_merge(
            $this->decodeTags($subcategory['theme_tags'] ?? null),
            [
                strtolower($style),
                strtolower(str_replace(' & ', ' ', (string) $paletteTokens['label'])),
                $ornament,
                $layout,
                strtolower(str_replace(' ', '-', $packLabel)),
            ]
        )));

        return [
            'code'           => sprintf('GEN-%05d', $index),
            'name'           => mb_substr($name, 0, 150),
            'layout'         => $layout,
            'palette'        => $palette,
            'font_pair'      => $fontPair,
            'preset'         => $preset,
            'type'           => $type,
            'motion'         => $motion,
            'style'          => $stylePack,
            // The motif was being put in the name and the tags but never in
            // the theme, so every generated card fell back to its pack's
            // motif. It now varies independently.
            'ornament'       => $ornament,
            'tags'           => $tags,
            'featured'       => false,
            'category_id'    => (int) $subcategory['category_id'],
            'subcategory_id' => (int) $subcategory['id'],
            'sort_order'     => 1000 + $index,
            'language'       => $language,
            'is_active'      => $activate,
        ];
    }

    private function presetForSubcategory(string $slug): string
    {
        return match (true) {
            str_contains($slug, 'engagement')            => 'engagement',
            str_contains($slug, 'reception')             => 'reception',
            str_contains($slug, 'birthday')              => 'birthday',
            str_contains($slug, 'anniversary')           => 'anniversary',
            str_contains($slug, 'baby') || str_contains($slug, 'naming')
                || str_contains($slug, 'mundan') || str_contains($slug, 'thread') => 'baby',
            str_contains($slug, 'griha') || str_contains($slug, 'housewarming') => 'housewarming',
            str_contains($slug, 'garba') || str_contains($slug, 'navratri') => 'garba',
            str_contains($slug, 'katha') || str_contains($slug, 'puja') || str_contains($slug, 'pooja')
                || str_contains($slug, 'mandir') || str_contains($slug, 'pratishtha')
                || str_contains($slug, 'mataji') || str_contains($slug, 'dhwaja')
                || str_contains($slug, 'religious')      => 'pooja',
            str_contains($slug, 'opening') || str_contains($slug, 'launch')
                || str_contains($slug, 'business')       => 'business',
            str_contains($slug, 'wedding') || str_contains($slug, 'kankotri')
                || str_contains($slug, 'theme') || str_contains($slug, 'haldi')
                || str_contains($slug, 'mehndi') || str_contains($slug, 'sangeet') => 'wedding',
            default                                      => 'event',
        };
    }

    /** @param array<int,string> $fontPairs */
    private function fontPairFor(string $preset, int $index, array $fontPairs): string
    {
        // Roughly a third of the catalogue in each Indic script, so language
        // filtering in the gallery has real results to show.
        $cycle = $index % 6;
        if ($cycle === 0) {
            return 'gujarati';
        }
        if ($cycle === 1) {
            return 'devanagari';
        }
        $latin = array_values(array_filter(
            $fontPairs,
            static fn (string $pair): bool => !in_array($pair, ['gujarati', 'devanagari'], true)
        ));
        return $latin[$index % max(1, count($latin))];
    }

    private function typeFor(string $layout, string $motion): string
    {
        if ($layout === 'envelope-3d') {
            return 'three_d';
        }
        if ($layout === 'multi-page-book') {
            return 'multi_page';
        }
        if ($layout === 'classic-kankotri' || $layout === 'temple-mandala') {
            return $motion === 'rich' ? 'animated' : 'kankotri';
        }
        return $motion === 'rich' ? 'animated' : 'static';
    }

    /** @return array<int,string> */
    private function decodeTags(mixed $value): array
    {
        if (is_array($value)) {
            return array_map('strval', $value);
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_map('strval', $decoded) : [];
    }

    private function nextGeneratedIndex(Database $db): int
    {
        $highest = (string) $db->value(
            'SELECT code FROM ' . $db->wrap($db->table('templates')) . "
             WHERE code LIKE 'GEN-%' ORDER BY code DESC LIMIT 1",
            [],
            ''
        );
        if ($highest === '') {
            return 1;
        }
        return ((int) substr($highest, 4)) + 1;
    }

    /** Remove every generated variant, leaving the curated catalogue intact. */
    public function removeGenerated(): array
    {
        $db = Database::instance();
        $ids = $db->column(
            'SELECT id FROM ' . $db->wrap($db->table('templates')) . " WHERE code LIKE 'GEN-%'"
        );
        if ($ids === []) {
            return ['removed' => 0, 'message' => 'There are no generated templates to remove.'];
        }

        // Keep any variant a user actually built an invitation from.
        $inUse = $db->column(
            'SELECT DISTINCT template_id FROM ' . $db->wrap($db->table('invitations'))
        );
        $inUseMap = array_fill_keys(array_map('intval', $inUse), true);

        $removed = 0;
        $kept = 0;
        foreach ($ids as $id) {
            if (isset($inUseMap[(int) $id])) {
                $kept++;
                continue;
            }
            $db->delete('templates', ['id' => (int) $id]);
            $removed++;
        }

        (new CategoryRepository())->refreshCounts();
        (new \App\Repositories\TemplateRepository())->flushCaches();
        AuditService::instance()->log('templates.remove_generated', 'template', null, $removed . ' removed');

        return [
            'removed' => $removed,
            'message' => $removed . ' generated template(s) removed'
                . ($kept > 0 ? ', ' . $kept . ' kept because invitations use them' : '') . '.',
        ];
    }

    public function generatedCount(): int
    {
        $db = Database::instance();
        return (int) $db->value(
            'SELECT COUNT(*) FROM ' . $db->wrap($db->table('templates')) . " WHERE code LIKE 'GEN-%'",
            [],
            0
        );
    }
}
