<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Url;
use App\Core\ValidationException;
use App\Repositories\CategoryRepository;
use App\Repositories\FontRepository;
use App\Repositories\SubcategoryRepository;
use App\Repositories\TemplateRepository;
use App\Seeds\FieldPresets;
use App\Seeds\ThemePalettes;
use App\Services\AuditService;
use App\Services\FeatureFlagService;
use App\Services\SeoService;
use App\Services\SitemapService;
use App\Services\TemplateEngine;
use App\Services\TemplateGeneratorService;

/**
 * Template management - the admin side of the template engine.
 *
 * A template is authored as data: layout, theme tokens, taxonomy and field
 * set. Raw HTML/CSS is available for a fully bespoke design and is sanitised
 * on render.
 */
final class TemplateController extends AdminController
{
    public function __construct(
        private readonly TemplateRepository $templates = new TemplateRepository(),
        private readonly CategoryRepository $categories = new CategoryRepository(),
        private readonly SubcategoryRepository $subcategories = new SubcategoryRepository(),
        private readonly TemplateEngine $engine = new TemplateEngine()
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'q'           => mb_substr((string) $request->query('q', ''), 0, 80),
            'category'    => $request->int('category'),
            'subcategory' => $request->int('subcategory'),
            'type'        => (string) $request->query('type', ''),
            'language'    => (string) $request->query('language', ''),
            'sort'        => (string) $request->query('sort', 'latest'),
            'active'      => $request->query('inactive') === '1' ? false : true,
        ];

        $result = $this->templates->search($filters, max(1, $request->int('page', 1)), 30);

        return $this->admin('admin.templates.index', 'Templates', [
            'templates'  => $result['rows'],
            'pagination' => $this->paginationMeta($result, Url::to('admin/templates'), array_filter($filters, static fn ($v) => $v !== '' && $v !== 0 && $v !== true)),
            'filters'    => $filters,
            'categories' => $this->categories->tree(false),
            'stats'      => $this->templates->stats(),
            'generatedCount' => (new TemplateGeneratorService())->generatedCount(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->admin('admin.templates.form', 'New template', $this->formData(null));
    }

    public function store(Request $request): Response
    {
        try {
            $data = $this->validate($request, $this->rules());
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $code = $this->templates->uniqueCode((string) ($data['code'] ?? 'TPL-' . Str::shortCode(4)));
        $slug = $this->templates->uniqueSlug(Str::slug((string) ($data['slug'] ?? $data['name'])));

        $id = $this->templates->create($this->payload($request, $data) + [
            'code'       => $code,
            'slug'       => $slug,
            'created_by' => Auth::id(),
            'view_count' => 0,
            'use_count'  => 0,
        ]);

        // A new template gets a working field set immediately.
        $preset = (string) $request->input('field_preset', 'wedding');
        if (array_key_exists($preset, FieldPresets::names())) {
            (new \App\Seeds\TemplateSeeder($this->templates->db()))->insertFields($id, $preset);
        }

        $this->afterChange($id);
        AuditService::instance()->log('admin.template.create', 'template', $id, $code . ' ' . $data['name']);

        return $this->respond($request, true, 'Template created. Review its fields next.', 'admin/templates/' . $id . '/fields');
    }

    public function edit(Request $request): Response
    {
        $template = $this->templates->find($request->int('id'));
        if ($template === null) {
            throw HttpException::notFound('That template does not exist.');
        }
        return $this->admin('admin.templates.form', 'Edit template', $this->formData($template));
    }

    public function update(Request $request): Response
    {
        $id = $request->int('id');
        $template = $this->templates->find($id);
        if ($template === null) {
            throw HttpException::notFound();
        }

        try {
            $data = $this->validate($request, $this->rules($id));
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $update = $this->payload($request, $data);
        if (isset($data['slug']) && Str::slug((string) $data['slug']) !== (string) $template['slug']) {
            $update['slug'] = $this->templates->uniqueSlug(Str::slug((string) $data['slug']), $id);
        }
        if (isset($data['code']) && (string) $data['code'] !== (string) $template['code']) {
            $update['code'] = $this->templates->uniqueCode((string) $data['code'], $id);
        }

        $this->templates->update($id, $update);
        $this->afterChange($id);
        AuditService::instance()->logChanges('admin.template.update', 'template', $id, $template, $update);

        return $this->respond($request, true, 'Template updated.', 'admin/templates/' . $id . '/edit');
    }

    public function duplicate(Request $request): Response
    {
        $id = $request->int('id');
        $template = $this->templates->find($id);
        if ($template === null) {
            throw HttpException::notFound();
        }

        $name = mb_substr((string) $template['name'] . ' (copy)', 0, 150);
        $newId = $this->templates->duplicate(
            $id,
            $name,
            $this->templates->uniqueCode((string) $template['code'] . '-C'),
            $this->templates->uniqueSlug(Str::slug($name)),
            Auth::id()
        );

        $this->afterChange($newId);
        AuditService::instance()->log('admin.template.duplicate', 'template', $newId, 'Copied from #' . $id);

        return $this->respond($request, true, 'Template duplicated.', 'admin/templates/' . $newId . '/edit');
    }

    public function toggle(Request $request): Response
    {
        $id = $request->int('id');
        $template = $this->templates->find($id);
        if ($template === null) {
            throw HttpException::notFound();
        }

        $active = (int) $template['is_active'] === 1 ? 0 : 1;
        $this->templates->update($id, ['is_active' => $active]);
        $this->afterChange($id);
        AuditService::instance()->log(
            'admin.template.toggle',
            'template',
            $id,
            $active === 1 ? 'Activated' : 'Deactivated'
        );

        return $this->respond(
            $request,
            true,
            $active === 1 ? 'Template activated.' : 'Template deactivated.',
            'admin/templates'
        );
    }

    public function destroy(Request $request): Response
    {
        $id = $request->int('id');
        $template = $this->templates->find($id);
        if ($template === null) {
            throw HttpException::notFound();
        }

        $db = $this->templates->db();
        $inUse = (int) $db->value(
            'SELECT COUNT(*) FROM ' . $db->wrap($db->table('invitations'))
            . ' WHERE template_id = :id AND deleted_at IS NULL',
            ['id' => $id],
            0
        );
        if ($inUse > 0 && !$request->bool('force')) {
            return $this->respond(
                $request,
                false,
                $inUse . ' invitation(s) use this template. Deactivate it instead, or confirm to delete.',
                'admin/templates'
            );
        }
        if ($inUse > 0) {
            // Soft delete keeps the row so existing invitations keep rendering.
            $this->templates->update($id, ['is_active' => 0]);
            $this->templates->delete($id);
            $this->afterChange($id);
            AuditService::instance()->log('admin.template.soft_delete', 'template', $id, (string) $template['code']);
            return $this->respond($request, true, 'Template hidden; existing invitations keep working.', 'admin/templates');
        }

        $this->templates->delete($id);
        $this->afterChange($id);
        AuditService::instance()->log('admin.template.delete', 'template', $id, (string) $template['code']);

        return $this->respond($request, true, 'Template deleted.', 'admin/templates');
    }

    /** Admin preview, rendered through the real engine with demo data. */
    public function preview(Request $request): Response
    {
        $template = $this->templates->definition($request->int('id'));
        if ($template === null) {
            throw HttpException::notFound();
        }

        $invitation = [
            'id'              => 0,
            'user_id'         => (int) Auth::id(),
            'template_id'     => (int) $template['id'],
            'category_id'     => $template['category_id'],
            'subcategory_id'  => $template['subcategory_id'],
            'title'           => (string) $template['name'],
            'slug'            => 'admin-preview',
            'short_code'      => 'PREVIEW',
            'status'          => 'draft',
            'language'        => (string) ($template['language'] === 'multi' ? Lang::locale() : $template['language']),
            'event_at'        => date('Y-m-d H:i:s', strtotime('+60 days')),
            'theme_overrides' => [],
            'settings'        => ['skip_animation' => true],
            'layout_key'      => $template['layout_key'],
            'custom_html'     => $template['custom_html'] ?? null,
            'custom_css'      => $template['custom_css'] ?? null,
            'custom_js'       => $template['custom_js'] ?? null,
        ];

        $context = $this->engine->context($invitation, null, true);

        return $this->view('invite.shell', [
            'c'         => $context,
            'body'      => $this->engine->render($invitation, null, true),
            'styles'    => $this->engine->styles($context),
            'scripts'   => $this->engine->scripts($context),
            'seo'       => SeoService::make()->title((string) $template['name'])->noindex(),
            'isPreview' => true,
            // A showcase: show the opening animation as a guest would see it.
            'liveEdit'  => false,
        ]);
    }

    // ------------------------------------------------------------------
    //  Variant generator
    // ------------------------------------------------------------------

    public function generator(Request $request): Response
    {
        $generator = new TemplateGeneratorService();

        return $this->admin('admin.templates.generate', 'Generate templates', [
            'stats'      => $this->templates->stats(),
            'generated'  => $generator->generatedCount(),
            'palettes'   => count(ThemePalettes::all()),
            'layouts'    => count(TemplateEngine::LAYOUTS),
            'subcategories' => count($this->subcategories->allWithCategory()),
            'enabled'    => FeatureFlagService::instance()->enabled('template_generator', true),
        ]);
    }

    public function generate(Request $request): Response
    {
        if (FeatureFlagService::instance()->disabled('template_generator', true)) {
            return $this->respond($request, false, 'The template generator is switched off.', 'admin/templates-generate');
        }

        $count = max(1, min(10000, $request->int('count', 250)));
        $result = (new TemplateGeneratorService())->generate(
            $count,
            $request->bool('activate', true),
            Auth::id()
        );

        return $this->respond($request, $result['created'] > 0, $result['message'], 'admin/templates-generate', $result);
    }

    public function removeGenerated(Request $request): Response
    {
        $result = (new TemplateGeneratorService())->removeGenerated();
        return $this->respond($request, true, $result['message'], 'admin/templates-generate', $result);
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function formData(?array $template): array
    {
        return [
            'template'      => $template,
            'categories'    => $this->categories->tree(false),
            'layouts'       => TemplateEngine::LAYOUTS,
            'palettes'      => ThemePalettes::all(),
            'fontPairs'     => ThemePalettes::fontPairs(),
            'fonts'         => (new FontRepository())->options(),
            'presets'       => FieldPresets::names(),
            'types'         => [
                'static'      => 'Static',
                'kankotri'    => 'Digital Kankotri',
                'multi_page'  => 'Multi-page',
                'animated'    => 'Animated',
                'three_d'     => '3D style',
                'interactive' => 'Interactive',
                'video'       => 'Video style',
            ],
        ];
    }

    /** @return array<string,string> */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'name'        => 'required|string|min:2|max:150|no_html',
            'code'        => 'nullable|string|max:40|alpha_dash',
            'slug'        => 'nullable|string|max:180',
            'description' => 'nullable|string|max:2000|safe_text',
            'category_id' => 'required|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'type'        => 'required|in:static,multi_page,animated,three_d,video,interactive,kankotri',
            'layout_key'  => 'required|string|max:60|alpha_dash',
            'language'    => 'required|in:en,gu,hi,multi',
            'page_count'  => 'nullable|integer|min:1|max:12',
            'palette'     => 'nullable|string|max:40|alpha_dash',
            'font_pair'   => 'nullable|string|max:40|alpha_dash',
            'motion'      => 'nullable|in:none,gentle,rich',
            'ornament'    => 'nullable|string|max:30|alpha_dash',
            'color_primary'    => 'nullable|hex_color',
            'color_secondary'  => 'nullable|hex_color',
            'color_background' => 'nullable|hex_color',
            'font_heading' => 'nullable|string|max:60',
            'font_body'    => 'nullable|string|max:60',
            'tags'         => 'nullable|string|max:500',
            'sort_order'   => 'nullable|integer|min:0|max:99999',
            'meta_title'   => 'nullable|string|max:191|no_html',
            'meta_description' => 'nullable|string|max:300|no_html',
            'custom_html'  => 'nullable|string|max:120000',
            'custom_css'   => 'nullable|string|max:60000',
            'custom_js'    => 'nullable|string|max:20000',
        ];
    }

    /** @return array<string,mixed> */
    private function payload(Request $request, array $data): array
    {
        $palette = (string) ($data['palette'] ?? 'kumkum-red');
        $fontPair = (string) ($data['font_pair'] ?? 'script-sans');
        $theme = ThemePalettes::themeFor(
            $palette,
            $fontPair,
            (string) ($data['motion'] ?? 'gentle'),
            isset($data['ornament']) ? (string) $data['ornament'] : null
        );

        // Explicit colour overrides win over the palette.
        foreach (['primary' => 'color_primary', 'secondary' => 'color_secondary', 'background' => 'color_background'] as $token => $field) {
            if (!empty($data[$field])) {
                $theme[$token] = (string) $data[$field];
            }
        }
        if (!empty($data['font_heading'])) {
            $theme['heading_font'] = (string) $data['font_heading'];
        }
        if (!empty($data['font_body'])) {
            $theme['body_font'] = (string) $data['font_body'];
        }

        $layout = (string) $data['layout_key'];
        if (!TemplateEngine::layoutExists($layout)) {
            $layout = 'classic-kankotri';
        }

        $type = (string) $data['type'];
        $tags = $this->parseTags((string) ($data['tags'] ?? ''));

        // Admin-authored markup is sanitised before it is stored, not only
        // when it is rendered.
        $customHtml = (string) ($data['custom_html'] ?? '');
        $customCss = (string) ($data['custom_css'] ?? '');

        return [
            'name'        => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'category_id' => (int) $data['category_id'],
            'subcategory_id' => empty($data['subcategory_id']) ? null : (int) $data['subcategory_id'],
            'type'        => $type,
            'layout_key'  => $layout,
            'language'    => (string) $data['language'],
            'orientation' => 'portrait',
            'page_count'  => (int) ($data['page_count'] ?? 1),
            'theme'       => $theme,
            'custom_html' => $customHtml === '' ? null : $this->engine->sanitiseHtml($customHtml),
            'custom_css'  => $customCss === '' ? null : $this->engine->sanitiseCss($customCss),
            'custom_js'   => empty($data['custom_js']) ? null : (string) $data['custom_js'],
            'tags'        => $tags,
            'search_keywords' => mb_substr(implode(' ', array_merge([(string) $data['name'], $layout, $type], $tags)), 0, 500),
            'color_primary'    => (string) $theme['primary'],
            'color_secondary'  => (string) $theme['secondary'],
            'color_background' => (string) $theme['background'],
            'font_heading'     => (string) $theme['heading_font'],
            'font_body'        => (string) $theme['body_font'],
            'supports_music'     => $request->bool('supports_music', true) ? 1 : 0,
            'supports_gallery'   => $request->bool('supports_gallery', true) ? 1 : 0,
            'supports_countdown' => $request->bool('supports_countdown', true) ? 1 : 0,
            'supports_rsvp'      => $request->bool('supports_rsvp', true) ? 1 : 0,
            'supports_map'       => $request->bool('supports_map', true) ? 1 : 0,
            'has_animation'      => in_array($type, ['animated', 'three_d'], true) ? 1 : 0,
            'is_premium'  => $request->bool('is_premium') ? 1 : 0,
            'is_active'   => $request->bool('is_active', true) ? 1 : 0,
            'is_featured' => $request->bool('is_featured') ? 1 : 0,
            'sort_order'  => (int) ($data['sort_order'] ?? 100),
            'meta_title'  => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ];
    }

    /** @return array<int,string> */
    private function parseTags(string $raw): array
    {
        $parts = preg_split('/[,\n]/u', $raw) ?: [];
        $tags = [];
        foreach ($parts as $part) {
            $tag = strtolower(trim((string) preg_replace('/[^\p{L}\p{N} \-]/u', '', $part)));
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
        return array_values(array_unique($tags));
    }

    private function afterChange(int $templateId): void
    {
        $this->templates->flushDefinition($templateId);
        $this->templates->flushCaches();
        $this->categories->refreshCounts();
        (new SitemapService())->flush();
    }
}
