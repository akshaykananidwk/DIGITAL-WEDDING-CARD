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
use App\Services\TemplateContext;
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
        $this->components();
        $this->designVariety();
        $this->suggestions();
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

    /**
     * Every template must look like its own card, not a recolour of another.
     *
     * The complaint this answers was concrete: a dozen templates shared the
     * mandir layout and differed only in colour, because the layout hardcoded
     * its motif and nothing else about the shape varied. Style packs are the
     * fix, so what is checked here is that they actually reach the page and
     * that no two templates on one layout wear the same one.
     */
    private function designVariety(): void
    {
        $db = Database::instance();
        $templates = new TemplateRepository();
        $engine = new TemplateEngine();

        // Every pack names a value for every axis, and only allowed values.
        foreach (ThemePalettes::stylePacks() as $slug => $pack) {
            foreach (TemplateContext::STYLE_AXES as $axis => $allowed) {
                $this->assertTrue(
                    'Variety: pack ' . $slug . ' sets a valid ' . $axis,
                    isset($pack[$axis]) && in_array($pack[$axis], $allowed, true),
                    (string) ($pack[$axis] ?? 'missing')
                );
            }
            $this->assertTrue(
                'Variety: pack ' . $slug . ' names a known motif',
                in_array($pack['ornament'] ?? '', TemplateContext::ORNAMENTS, true)
            );
        }

        /*
         * No two templates on a layout share a pack. Counted in PHP rather
         * than with SQL JSON functions, because the schema builder targets
         * SQLite as well as MySQL and their JSON syntax differs.
         */
        $rows = $db->select(
            'SELECT layout_key, theme FROM ' . $db->wrap($db->table('templates')) . ' WHERE deleted_at IS NULL'
        );
        $seen = [];
        $collisions = [];
        foreach ($rows as $row) {
            $theme = is_array($row['theme']) ? $row['theme'] : json_decode((string) $row['theme'], true);
            $pack = is_array($theme) ? (string) ($theme['style'] ?? '') : '';
            $key = (string) $row['layout_key'] . '|' . $pack;
            if (isset($seen[$key])) {
                $collisions[] = $key;
            }
            $seen[$key] = true;
        }
        $this->assertSame(
            'Variety: no two templates on one layout share a style pack',
            [],
            $collisions
        );

        // And the axes reach the rendered page.
        $invitation = $db->first(
            'SELECT * FROM ' . $db->wrap($db->table('invitations')) . ' WHERE deleted_at IS NULL LIMIT 1'
        );
        if ($invitation === null) {
            $this->pass('Variety: skipped rendering, no invitation present');
            return;
        }
        $row = (array) (new \App\Repositories\InvitationRepository())->find((int) $invitation['id']);
        $context = $engine->context($row, null, true);

        $this->assertMatches(
            'Variety: the style axes are exposed as data attributes',
            '/data-frame="[a-z]+" data-pattern="[a-z]+" data-divider="[a-z]+"/',
            $context->styleAttributes()
        );
        foreach (array_keys(TemplateContext::STYLE_AXES) as $axis) {
            $this->assertTrue(
                'Variety: ' . $axis . ' resolves to an allowed value',
                in_array($context->style($axis), TemplateContext::STYLE_AXES[$axis], true)
            );
        }

        // A motif named by the template wins over the layout's own default,
        // which is what stopped a dozen cards sharing one icon.
        $this->assertSame(
            'Variety: a template motif overrides the layout default',
            (string) $context->ornament(),
            $context->ornamentOr('temple')
        );
        /*
         * The shape belongs to the template, not to the person filling it in:
         * a theme override naming a frame is ignored, so a card cannot be
         * restyled through the builder's colour controls.
         */
        $this->assertSame(
            'Variety: a user cannot override the structural axes',
            $context->style('frame'),
            $engine->context(
                array_merge($row, ['theme_overrides' => ['frame' => 'arch']]),
                null,
                true
            )->style('frame')
        );

        // And a value that is not on the allowed list is refused rather than
        // printed into the attribute. Poked into the template itself, which is
        // the only place such a value could come from, then put back.
        $templateId = (int) $row['template_id'];
        $original = (string) $db->value(
            'SELECT theme FROM ' . $db->wrap($db->table('templates')) . ' WHERE id = :id',
            ['id' => $templateId]
        );
        try {
            $poisoned = json_decode($original, true);
            $poisoned['frame'] = 'bogus" onload="alert(1)';
            $poisoned['counter'] = 'nonsense';
            $db->update('templates', ['theme' => json_encode($poisoned)], ['id' => $templateId]);
            $templates->flushDefinition($templateId);

            $poisonedContext = $engine->context(
                (array) (new \App\Repositories\InvitationRepository())->find((int) $invitation['id']),
                null,
                true
            );
            $this->assertSame(
                'Variety: an unknown frame falls back to the first allowed value',
                'plain',
                $poisonedContext->style('frame')
            );
            $this->assertSame(
                'Variety: an unknown counter falls back too',
                'boxes',
                $poisonedContext->style('counter')
            );
            $this->assertNotContains(
                'Variety: nothing can break out of the attribute',
                'onload',
                $poisonedContext->styleAttributes()
            );
        } finally {
            $db->update('templates', ['theme' => $original], ['id' => $templateId]);
            $templates->flushDefinition($templateId);
        }

        // Two templates on the same layout must differ in more than colour.
        $pair = [];
        foreach ($db->select(
            'SELECT slug, theme FROM ' . $db->wrap($db->table('templates')) . '
             WHERE layout_key = :layout AND deleted_at IS NULL ORDER BY sort_order',
            ['layout' => 'temple-mandala']
        ) as $candidate) {
            $theme = is_array($candidate['theme'])
                ? $candidate['theme']
                : json_decode((string) $candidate['theme'], true);
            if (is_array($theme)) {
                $pair[] = ['slug' => (string) $candidate['slug']] + $theme;
            }
            if (count($pair) === 2) {
                break;
            }
        }
        if (count($pair) === 2) {
            $differs = ($pair[0]['frame'] ?? '') !== ($pair[1]['frame'] ?? '')
                || ($pair[0]['pattern'] ?? '') !== ($pair[1]['pattern'] ?? '')
                || ($pair[0]['ornament'] ?? '') !== ($pair[1]['ornament'] ?? '');
            $this->assertTrue(
                'Variety: two cards on one layout differ in shape, not just colour',
                $differs,
                $pair[0]['slug'] . ' vs ' . $pair[1]['slug']
            );
        }
    }

    /** The gallery search box's suggestions. */
    private function suggestions(): void
    {
        $recommender = new \App\Services\TemplateRecommenderService();

        $this->assertSame(
            'Suggestions: a single character suggests nothing',
            [],
            $recommender->suggestions('k')
        );

        $found = $recommender->suggestions('kank');
        $this->assertTrue('Suggestions: a known word returns matches', $found !== []);
        $this->assertTrue(
            'Suggestions: every suggestion is a name, not a row',
            array_filter($found, static fn ($name): bool => !is_string($name) || $name === '') === []
        );
        $this->assertSame(
            'Suggestions: there are no duplicates',
            count($found),
            count(array_unique($found))
        );
        $this->assertSame(
            'Suggestions: nonsense returns nothing',
            [],
            $recommender->suggestions('zzzqqxnothing')
        );
    }

    /**
     * A template built from components renders from those blocks instead of
     * its layout, and goes back to the layout when they are gone.
     */
    private function components(): void
    {
        $db = Database::instance();
        $invitation = $db->first(
            'SELECT * FROM ' . $db->wrap($db->table('invitations')) . ' WHERE deleted_at IS NULL LIMIT 1'
        );
        if ($invitation === null) {
            $this->pass('Components: skipped, no invitation present');
            return;
        }

        $invitations = new \App\Repositories\InvitationRepository();
        $templates = new TemplateRepository();
        $components = new \App\Repositories\TemplateComponentRepository();
        $engine = new TemplateEngine();
        $templateId = (int) $invitation['template_id'];

        $made = [];
        try {
            $made[] = $components->create([
                'template_id'   => $templateId,
                'component_key' => 'suite_heading',
                'name'          => 'Suite heading',
                'type'          => 'heading',
                'content'       => '<p class="suite-heading">{{groom_name}}</p>',
                'styles'        => ['text-align' => 'center'],
                'page_number'   => 1,
                'sort_order'    => 10,
                'is_visible'    => 1,
            ]);
            $made[] = $components->create([
                'template_id'   => $templateId,
                'component_key' => 'suite_second_page',
                'name'          => 'Suite second page',
                'type'          => 'section',
                'content'       => '<p class="suite-second">{{venue_name}}</p>',
                'page_number'   => 2,
                'sort_order'    => 10,
                'is_visible'    => 1,
            ]);
            $made[] = $components->create([
                'template_id'   => $templateId,
                'component_key' => 'suite_hidden',
                'name'          => 'Suite hidden',
                'type'          => 'text',
                'content'       => '<p class="suite-hidden">hidden</p>',
                'page_number'   => 3,
                'sort_order'    => 10,
                'is_visible'    => 0,
            ]);
            $templates->flushDefinition($templateId);

            $row = (array) $invitations->find((int) $invitation['id']);
            $html = $engine->render($row, null, true);

            $this->assertContains('Components: a component is rendered', 'inv-component--heading', $html);
            $this->assertContains('Components: its placeholder is resolved', 'suite-heading', $html);
            $this->assertNotContains('Components: no placeholder survives', '{{groom_name}}', $html);
            $this->assertContains('Components: its styles reach the wrapper', 'text-align:center', $html);
            $this->assertNotContains('Components: a hidden component is left out', 'suite-hidden', $html);
            $this->assertContains('Components: two pages become a page-turning card', 'inv-book__page', $html);
            $this->assertSame(
                'Components: only the visible pages are rendered',
                2,
                substr_count($html, 'inv-book__page')
            );

            // Markup the sanitiser must not pass through, even from an admin.
            $components->update($made[0], [
                'content' => '<p onclick="x()">{{groom_name}}</p><script>alert(1)</script>',
            ]);
            $templates->flushDefinition($templateId);
            $html = $engine->render((array) $invitations->find((int) $invitation['id']), null, true);
            $this->assertNotContains('Components: a script tag is stripped on render', '<script', $html);
            $this->assertNotContains('Components: an inline handler is stripped on render', 'onclick', $html);
        } finally {
            foreach ($made as $id) {
                $components->forceDelete((int) $id);
            }
            $templates->flushDefinition($templateId);
        }

        $html = $engine->render((array) $invitations->find((int) $invitation['id']), null, true);
        $this->assertNotContains('Components: removing them restores the layout', 'inv-component--', $html);
        $this->assertContains('Components: the layout renders again', 'inv-', $html);
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
