<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\TemplateFieldRepository;
use App\Repositories\TemplateRepository;

final class TemplateApiController extends Controller
{
    public function __construct(
        private readonly TemplateRepository $templates = new TemplateRepository()
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
            'animated'    => $request->query('animated') === '1' ? '1' : '',
            'sort'        => (string) $request->query('sort', 'popular'),
            'active'      => true,
        ];

        $result = $this->templates->search(
            $filters,
            max(1, $request->int('page', 1)),
            max(1, min(60, $request->int('per_page', 24)))
        );

        return $this->success(
            array_map([$this, 'transformCard'], $result['rows']),
            '',
            [
                'page'     => $result['page'],
                'pages'    => $result['pages'],
                'per_page' => $result['per_page'],
                'total'    => $result['total'],
            ]
        );
    }

    public function show(Request $request): Response
    {
        $template = $this->templates->findBySlug((string) $request->param('slug'));
        if ($template === null) {
            return $this->error('Template not found.', 404);
        }

        $fields = (new TemplateFieldRepository())->forTemplate((int) $template['id'], true);

        return $this->success([
            'id'          => (int) $template['id'],
            'code'        => (string) $template['code'],
            'name'        => (string) $template['name'],
            'slug'        => (string) $template['slug'],
            'description' => (string) ($template['description'] ?? ''),
            'type'        => (string) $template['type'],
            'layout'      => (string) $template['layout_key'],
            'language'    => (string) $template['language'],
            'pages'       => (int) $template['page_count'],
            'theme'       => $template['theme'],
            'tags'        => $template['tags'],
            'colors'      => [
                'primary'    => (string) $template['color_primary'],
                'secondary'  => (string) $template['color_secondary'],
                'background' => (string) $template['color_background'],
            ],
            'supports' => [
                'music'     => (int) $template['supports_music'] === 1,
                'gallery'   => (int) $template['supports_gallery'] === 1,
                'countdown' => (int) $template['supports_countdown'] === 1,
                'rsvp'      => (int) $template['supports_rsvp'] === 1,
                'map'       => (int) $template['supports_map'] === 1,
            ],
            'preview_url' => Url::to('templates/' . $template['slug'] . '/preview'),
            'fields'      => array_map(static fn (array $field): array => [
                'key'         => (string) $field['field_key'],
                'label'       => (string) $field['label'],
                'label_gu'    => (string) ($field['label_gu'] ?? ''),
                'label_hi'    => (string) ($field['label_hi'] ?? ''),
                'type'        => (string) $field['type'],
                'section'     => (string) $field['section'],
                'required'    => (int) $field['is_required'] === 1,
                'placeholder' => (string) ($field['placeholder'] ?? ''),
                'help'        => (string) ($field['help_text'] ?? ''),
                'options'     => $field['options'] ?? [],
                'max_length'  => $field['max_length'] === null ? null : (int) $field['max_length'],
                'ai'          => (int) $field['is_ai_generatable'] === 1,
            ], $fields),
        ]);
    }

    /** @return array<string,mixed> */
    private function transformCard(array $template): array
    {
        return [
            'id'        => (int) $template['id'],
            'code'      => (string) $template['code'],
            'name'      => (string) $template['name'],
            'slug'      => (string) $template['slug'],
            'type'      => (string) $template['type'],
            'language'  => (string) $template['language'],
            'animated'  => (int) $template['has_animation'] === 1,
            'premium'   => (int) $template['is_premium'] === 1,
            'featured'  => (int) $template['is_featured'] === 1,
            'pages'     => (int) $template['page_count'],
            'uses'      => (int) $template['use_count'],
            'tags'      => $template['tags'],
            'colors'    => [
                'primary'    => (string) $template['color_primary'],
                'secondary'  => (string) $template['color_secondary'],
                'background' => (string) $template['color_background'],
            ],
            'preview_url' => Url::to('templates/' . $template['slug'] . '/preview'),
            'detail_url'  => Url::to('templates/' . $template['slug']),
        ];
    }
}
