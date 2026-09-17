<?php

declare(strict_types=1);

namespace Tests\Cases;

use App\Core\Database;
use App\Repositories\CategoryRepository;
use App\Repositories\SubcategoryRepository;
use App\Repositories\TemplateFieldRepository;
use App\Repositories\TemplateRepository;
use App\Seeds\FieldPresets;
use App\Seeds\ThemePalettes;
use App\Services\TemplateEngine;
use App\Services\TemplateGeneratorService;
use Tests\TestCase;

/**
 * Section 60: the template engine, the catalogue and its scalability.
 *
 * The scalability check generates a real batch of templates, queries the
 * catalogue at that size and then removes what it created.
 */
final class TemplateTest extends TestCase
{
    private const GENERATE = 1000;

    public function name(): string
    {
        return 'Templates and the engine';
    }

    public function run(): void
    {
        $this->catalogue();
        $this->fields();
        $this->engine();
        $this->placeholders();
        $this->scalability();
    }

    private function catalogue(): void
    {
        $templates = new TemplateRepository();
        $categories = new CategoryRepository();
        $subcategories = new SubcategoryRepository();

        $stats = $templates->stats();
        $this->assertGreaterThan('Catalogue: templates are seeded', 10, (float) $stats['total']);
        $this->assertGreaterThan('Catalogue: categories are seeded', 4, (float) count($categories->tree(false)));
        $this->assertGreaterThan(
            'Catalogue: the Gujarati occasion list is seeded',
            50,
            (float) count($subcategories->allWithCategory())
        );

        $first = $templates->search(['active' => true], 1, 1)['rows'][0] ?? null;
        $this->assertTrue('Catalogue: a template can be listed', $first !== null);

        $bySlug = $templates->findBySlug((string) ($first['slug'] ?? ''));
        $this->assertTrue('Catalogue: a template can be fetched by slug', $bySlug !== null);

        $filtered = $templates->search(['language' => 'gu', 'active' => true], 1, 5);
        foreach ($filtered['rows'] as $row) {
            $this->assertTrue(
                'Catalogue: the language filter is honoured',
                in_array((string) $row['language'], ['gu', 'multi'], true),
                (string) $row['language']
            );
            break;
        }

        $search = $templates->search(['q' => 'kankotri', 'active' => true], 1, 5);
        $this->assertTrue('Catalogue: full-text search returns rows', is_array($search['rows']));
    }

    private function fields(): void
    {
        $fields = new TemplateFieldRepository();
        $templates = new TemplateRepository();
        $template = $templates->search(['active' => true], 1, 1)['rows'][0] ?? [];
        $templateId = (int) ($template['id'] ?? 0);

        $definitions = $fields->forTemplate($templateId, true);
        $this->assertGreaterThan('Fields: the template has editable fields', 5, (float) count($definitions));

        $sections = $fields->bySection($templateId);
        $this->assertTrue('Fields: fields are grouped into sections', $sections !== []);
        foreach (array_keys($sections) as $section) {
            $this->assertTrue(
                'Fields: section "' . $section . '" is a known section',
                array_key_exists((string) $section, FieldPresets::SECTIONS)
            );
        }

        $types = array_unique(array_map(static fn (array $f): string => (string) $f['type'], $definitions));
        $this->assertGreaterThan('Fields: several field types are in use', 2, (float) count($types));

        $presets = FieldPresets::names();
        $this->assertGreaterThan('Fields: occasion presets exist', 8, (float) count($presets));
        foreach (array_keys($presets) as $preset) {
            $set = FieldPresets::get((string) $preset);
            $this->assertGreaterThan(
                'Fields: preset "' . $preset . '" defines fields',
                3,
                (float) count($set)
            );
        }
    }

    private function engine(): void
    {
        $this->assertSame('Engine: ten layouts are registered', 10, count(TemplateEngine::LAYOUTS));
        foreach (array_keys(TemplateEngine::LAYOUTS) as $layout) {
            $this->assertTrue(
                'Engine: layout "' . $layout . '" has a renderer',
                is_file(ROOT_PATH . '/app/Views/invite/layouts/' . $layout . '.php')
            );
            $this->assertTrue('Engine: layoutExists("' . $layout . '")', TemplateEngine::layoutExists((string) $layout));
        }
        $this->assertFalse('Engine: an unknown layout is rejected', TemplateEngine::layoutExists('../../etc/passwd'));

        $palettes = ThemePalettes::all();
        $this->assertGreaterThan('Engine: palettes are defined', 10, (float) count($palettes));
        foreach ($palettes as $key => $palette) {
            foreach (['primary', 'secondary', 'background', 'text'] as $token) {
                $this->assertMatches(
                    'Engine: palette "' . $key . '" has a valid ' . $token,
                    '/^#[0-9A-Fa-f]{6}$/',
                    (string) $palette[$token]
                );
            }
            break;
        }

        // The theme override whitelist is what stops a user injecting CSS.
        $engine = new TemplateEngine();
        $allowed = $engine->allowedOverrides();
        $this->assertTrue('Engine: colour overrides are validated', isset($allowed['primary']));
        $this->assertTrue('Engine: a valid colour passes', $allowed['primary']('#C8102E'));
        $this->assertFalse('Engine: a CSS payload is rejected', $allowed['primary']('red; background:url(//evil)'));
        $this->assertFalse('Engine: a font payload is rejected', $allowed['heading_font']('Noto", url(//evil)'));
        $this->assertFalse('Engine: an out-of-range scale is rejected', $allowed['heading_scale']('9'));
    }

    private function placeholders(): void
    {
        $db = Database::instance();
        $invitation = $db->first(
            'SELECT * FROM ' . $db->wrap($db->table('invitations')) . ' WHERE deleted_at IS NULL LIMIT 1'
        );
        if ($invitation === null) {
            $this->pass('Engine: skipped placeholder rendering, no invitation present');
            return;
        }

        $invitations = new \App\Repositories\InvitationRepository();
        $row = (array) $invitations->find((int) $invitation['id']);

        $engine = new TemplateEngine();
        $context = $engine->context($row, null, true);
        $html = $engine->render($row, null, true);

        $this->assertGreaterThan('Engine: the invitation renders to HTML', 500, (float) strlen($html));
        $this->assertContains('Engine: the render includes the invitation body', 'inv-', $html);
        $this->assertNotContains('Engine: no unresolved placeholders remain', '{{', $html);

        // styles() carries the @font-face rules; the per-invitation design
        // tokens come from the context as inline custom properties.
        $styles = $engine->styles($context);
        $this->assertContains('Engine: bundled font faces are emitted', '@font-face', $styles);
        $this->assertContains('Engine: CSS custom properties are emitted', '--inv-primary', $context->cssVariables());
        $this->assertMatches(
            'Engine: the primary token is a colour',
            '/--inv-primary:\s*#[0-9A-Fa-f]{3,6}/',
            $context->cssVariables()
        );

        // A placeholder value is escaped, not interpolated raw.
        $resolved = $engine->renderCustomHtml(
            '<p>{{groom_name}}</p>',
            $engine->context(array_merge($row, ['custom_html' => null]), ['groom_name' => '<b>x</b>'], true)
        );
        $this->assertNotContains('Engine: a placeholder value cannot inject markup', '<b>x</b>', $resolved);
    }

    private function scalability(): void
    {
        $templates = new TemplateRepository();
        $generator = new TemplateGeneratorService();
        $before = (int) $templates->stats()['total'];

        $started = microtime(true);
        $result = $generator->generate(self::GENERATE, true, null);
        $generateMs = (int) round((microtime(true) - $started) * 1000);

        $created = (int) ($result['created'] ?? 0);
        $this->assertGreaterThan('Scalability: a large batch is generated', 900, (float) $created);
        $this->assertTrue(
            'Scalability: ' . number_format($created) . ' templates generated in ' . $generateMs . 'ms',
            $generateMs < 120000,
            $generateMs . 'ms'
        );

        $after = (int) $templates->stats()['total'];
        $this->assertGreaterThan('Scalability: the catalogue grew', (float) $before, (float) $after);
        $this->assertGreaterThan('Scalability: the catalogue holds 1000+ templates', 1000, (float) $after);

        // Listing must stay fast and must not load the whole catalogue.
        $started = microtime(true);
        $page = $templates->search(['active' => true, 'sort' => 'popular'], 7, 24);
        $listMs = (int) round((microtime(true) - $started) * 1000);
        $this->assertSame('Scalability: a page holds one page of rows', 24, count($page['rows']));
        $this->assertTrue(
            'Scalability: page 7 of ' . number_format($after) . ' templates took ' . $listMs . 'ms',
            $listMs < 1500,
            $listMs . 'ms'
        );

        $started = microtime(true);
        $found = $templates->search(['q' => 'kankotri', 'active' => true], 1, 24);
        $searchMs = (int) round((microtime(true) - $started) * 1000);
        $this->assertTrue(
            'Scalability: searching that catalogue took ' . $searchMs . 'ms',
            $searchMs < 1500,
            $searchMs . 'ms'
        );
        $this->assertTrue('Scalability: search still returns rows', $found['rows'] !== []);

        // Unique codes and slugs at that volume.
        $db = Database::instance();
        $duplicateSlugs = (int) $db->value(
            'SELECT COUNT(*) FROM (SELECT slug FROM ' . $db->wrap($db->table('templates'))
            . ' GROUP BY slug HAVING COUNT(*) > 1) d',
            [],
            0
        );
        $this->assertSame('Scalability: every slug is unique', 0, $duplicateSlugs);

        // Put the catalogue back the way it was.
        $removal = $generator->removeGenerated();
        $this->assertTrue('Scalability: generated templates are removable', (bool) ($removal['ok'] ?? true));
        $this->assertSame(
            'Scalability: the catalogue is back to its original size',
            $before,
            (int) $templates->stats()['total']
        );
    }
}
