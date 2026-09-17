<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\CategoryRepository;
use App\Repositories\SubcategoryRepository;
use App\Repositories\TemplateRepository;
use App\Services\SeoService;
use App\Services\TemplateEngine;

/**
 * The public template gallery.
 *
 * Paginated, filtered and index-backed: the listing never loads the heavy
 * template columns and never selects more than one page of rows, which is
 * what keeps it fast with a catalogue of thousands.
 */
final class TemplateBrowseController extends Controller
{
    private const PER_PAGE = 24;

    public function __construct(
        private readonly TemplateRepository $templates = new TemplateRepository(),
        private readonly CategoryRepository $categories = new CategoryRepository(),
        private readonly SubcategoryRepository $subcategories = new SubcategoryRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $this->filtersFrom($request);
        $page = max(1, $request->int('page', 1));
        $result = $this->templates->search($filters, $page, self::PER_PAGE);

        $query = array_filter($filters, static fn ($value) => $value !== '' && $value !== null && $value !== true);
        unset($query['active']);

        return $this->view('templates.index', [
            'seo' => SeoService::make()
                ->title(Lang::get('templates.title'))
                ->description(Lang::get('templates.subtitle'))
                ->canonical(Url::to('templates'))
                ->withLocaleAlternates('templates'),
            'templates'  => $result['rows'],
            'pagination' => $this->paginationMeta($result, Url::to('templates'), $query),
            'filters'    => $filters,
            'categories' => $this->categories->tree(),
            'palette'    => $this->templates->paletteOptions(),
            'total'      => $result['total'],
        ]);
    }

    public function show(Request $request): Response
    {
        $template = $this->templates->findBySlug((string) $request->param('slug'));
        if ($template === null) {
            throw HttpException::notFound('That template is no longer available.');
        }

        $this->templates->incrementViews((int) $template['id']);

        $category = $this->categories->find((int) $template['category_id']);
        $subcategory = $template['subcategory_id'] === null
            ? null
            : $this->subcategories->find((int) $template['subcategory_id']);

        return $this->view('templates.show', [
            'seo'         => SeoService::forTemplate($template),
            'template'    => $template,
            'category'    => $category,
            'subcategory' => $subcategory,
            'related'     => $this->templates->related($template, 6),
            'layoutName'  => TemplateEngine::LAYOUTS[(string) $template['layout_key']] ?? 'Custom',
        ]);
    }

    /**
     * Full-page preview of a template, filled with its demo data.
     *
     * Rendered through the same engine that renders a real invitation, so
     * what a visitor previews is exactly what they will get.
     */
    public function preview(Request $request): Response
    {
        $template = $this->templates->findBySlug((string) $request->param('slug'));
        if ($template === null) {
            throw HttpException::notFound();
        }

        $engine = new TemplateEngine();
        $invitation = $this->demoInvitation($template);
        $context = $engine->context($invitation, null, true);

        return $this->view('invite.shell', [
            'c'        => $context,
            'body'     => $engine->render($invitation, null, true),
            'styles'   => $engine->styles($context),
            'scripts'  => $engine->scripts($context),
            'seo'      => SeoService::forTemplate($template)->noindex(),
            'isPreview' => true,
        ]);
    }

    /** A throwaway invitation row so a template can be previewed standalone. */
    private function demoInvitation(array $template): array
    {
        return [
            'id'              => 0,
            'user_id'         => 0,
            'template_id'     => (int) $template['id'],
            'category_id'     => $template['category_id'],
            'subcategory_id'  => $template['subcategory_id'],
            'title'           => (string) $template['name'],
            'slug'            => 'preview-' . $template['slug'],
            'short_code'      => 'PREVIEW',
            'status'          => 'draft',
            'language'        => (string) ($template['language'] === 'multi' ? Lang::locale() : $template['language']),
            'event_date'      => null,
            'event_time'      => null,
            'event_at'        => date('Y-m-d H:i:s', strtotime('+90 days')),
            'theme_overrides' => [],
            'settings'        => [
                'show_countdown' => true,
                'show_rsvp'      => true,
                'show_gallery'   => false,
                'show_share'     => false,
                'show_qr'        => false,
                'skip_animation' => false,
            ],
            'meta_title'       => (string) $template['name'],
            'meta_description' => (string) ($template['meta_description'] ?? ''),
            'view_count'       => 0,
            'layout_key'       => $template['layout_key'],
            'template_type'    => $template['type'],
            'custom_html'      => $template['custom_html'] ?? null,
            'custom_css'       => $template['custom_css'] ?? null,
            'custom_js'        => $template['custom_js'] ?? null,
        ];
    }

    /** @return array<string,mixed> */
    private function filtersFrom(Request $request): array
    {
        $categorySlug = (string) $request->query('category', '');
        $subcategorySlug = (string) $request->query('subcategory', '');

        $categoryId = 0;
        $subcategoryId = 0;
        if ($categorySlug !== '') {
            $category = $this->categories->findBySlug($categorySlug);
            $categoryId = $category === null ? 0 : (int) $category['id'];
        }
        if ($subcategorySlug !== '') {
            $subcategory = $this->subcategories->findBySlug($subcategorySlug);
            $subcategoryId = $subcategory === null ? 0 : (int) $subcategory['id'];
        }

        $sort = (string) $request->query('sort', 'popular');
        $type = (string) $request->query('type', '');

        return [
            'q'           => mb_substr((string) $request->query('q', ''), 0, 80),
            'category'    => $categoryId,
            'subcategory' => $subcategoryId,
            'category_slug' => $categorySlug,
            'subcategory_slug' => $subcategorySlug,
            'type'        => in_array($type, ['static', 'multi_page', 'animated', 'three_d', 'video', 'interactive', 'kankotri'], true) ? $type : '',
            'language'    => in_array($request->query('language'), ['en', 'gu', 'hi'], true) ? (string) $request->query('language') : '',
            'animated'    => $request->query('animated') === '1' ? '1' : '',
            'premium'     => in_array($request->query('premium'), ['0', '1'], true) ? (string) $request->query('premium') : '',
            'color'       => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $request->query('color', '')) === 1
                ? (string) $request->query('color')
                : '',
            'tag'         => preg_replace('/[^a-z0-9 \-]/i', '', (string) $request->query('tag', '')) ?? '',
            'sort'        => in_array($sort, ['popular', 'latest', 'name', 'rating', 'featured'], true) ? $sort : 'popular',
            'active'      => true,
        ];
    }
}
