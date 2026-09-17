<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\TemplateComponentRepository;
use App\Repositories\TemplateFieldRepository;
use App\Repositories\TemplateRepository;
use App\Services\AuditService;
use App\Services\TemplateEngine;

/**
 * Template components.
 *
 * The other way to build a template. A layout renderer covers the common
 * shapes; components are for a bespoke card whose blocks and page order are
 * decided here rather than in PHP. Content may carry {{field_key}}
 * placeholders and is sanitised by the engine before it ever reaches a guest.
 */
final class TemplateComponentController extends AdminController
{
    /** The component types the schema allows, with names an operator reads. */
    private const TYPES = [
        'page' => 'Page', 'section' => 'Section', 'heading' => 'Heading',
        'text' => 'Text', 'names' => 'Names', 'divider' => 'Divider',
        'image' => 'Image', 'gallery' => 'Gallery', 'countdown' => 'Countdown',
        'map' => 'Map', 'rsvp' => 'RSVP', 'family' => 'Family', 'events' => 'Events',
        'quote' => 'Quote', 'ornament' => 'Ornament', 'music' => 'Music',
        'share' => 'Share', 'qr' => 'QR', 'custom' => 'Custom',
    ];

    public function __construct(
        private readonly TemplateRepository $templates = new TemplateRepository(),
        private readonly TemplateComponentRepository $components = new TemplateComponentRepository(),
        private readonly TemplateFieldRepository $fields = new TemplateFieldRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        $template = $this->requireTemplate($request);
        $components = $this->components->forTemplate((int) $template['id']);

        return $this->admin('admin.templates.components', 'Components · ' . $template['name'], [
            'template'   => $template,
            'components' => $components,
            'pages'      => $this->components->byPage((int) $template['id']),
            'types'      => self::TYPES,
            // The placeholders an operator may use in the content box.
            'fieldKeys'  => array_map(
                static fn (array $field): string => (string) $field['field_key'],
                $this->fields->forTemplate((int) $template['id'])
            ),
        ]);
    }

    public function store(Request $request): Response
    {
        $template = $this->requireTemplate($request);

        try {
            $data = $this->validate($request, $this->rules());
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $key = $this->normaliseKey((string) ($data['component_key'] ?? ''), (string) $data['name']);
        $id = $this->components->create($this->payload($request, $data, true) + [
            'template_id'   => (int) $template['id'],
            'component_key' => $key,
        ]);

        $this->templates->flushDefinition((int) $template['id']);
        AuditService::instance()->log('admin.template.component_create', 'template_component', $id, $key);

        return $this->respond(
            $request,
            true,
            'Component added.',
            'admin/templates/' . $template['id'] . '/components'
        );
    }

    public function update(Request $request): Response
    {
        $template = $this->requireTemplate($request);
        $component = $this->requireComponent($request, $template);

        try {
            $data = $this->validate($request, $this->rules());
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $this->components->update((int) $component['id'], $this->payload($request, $data, false));
        $this->templates->flushDefinition((int) $template['id']);
        AuditService::instance()->log(
            'admin.template.component_update',
            'template_component',
            (int) $component['id'],
            (string) $component['component_key']
        );

        return $this->respond(
            $request,
            true,
            'Component updated.',
            'admin/templates/' . $template['id'] . '/components'
        );
    }

    public function reorder(Request $request): Response
    {
        $template = $this->requireTemplate($request);
        $order = array_map('intval', $request->array('order'));
        if ($order !== []) {
            $this->components->reorder((int) $template['id'], $order);
            $this->templates->flushDefinition((int) $template['id']);
        }
        return $this->respond(
            $request,
            true,
            'Order saved.',
            'admin/templates/' . $template['id'] . '/components'
        );
    }

    public function destroy(Request $request): Response
    {
        $template = $this->requireTemplate($request);
        $component = $this->requireComponent($request, $template);

        $this->components->forceDelete((int) $component['id']);
        $this->templates->flushDefinition((int) $template['id']);
        AuditService::instance()->log(
            'admin.template.component_delete',
            'template_component',
            (int) $component['id'],
            (string) $component['component_key']
        );

        return $this->respond(
            $request,
            true,
            'Component removed.',
            'admin/templates/' . $template['id'] . '/components'
        );
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function requireTemplate(Request $request): array
    {
        $template = $this->templates->find($request->int('id'));
        if ($template === null) {
            throw HttpException::notFound('That template does not exist.');
        }
        return $template;
    }

    /**
     * @param  array<string,mixed> $template
     * @return array<string,mixed>
     */
    private function requireComponent(Request $request, array $template): array
    {
        $component = $this->components->find($request->int('componentId'));
        if ($component === null || (int) $component['template_id'] !== (int) $template['id']) {
            throw HttpException::notFound('That component does not exist.');
        }
        return $component;
    }

    /** @return array<string,string> */
    private function rules(): array
    {
        return [
            'name'          => 'required|string|max:120|no_html',
            'component_key' => 'nullable|string|max:64|alpha_dash',
            'type'          => 'required|in:' . implode(',', array_keys(self::TYPES)),
            // Markup is allowed here and sanitised on render, exactly like the
            // template's own custom_html.
            'content'       => 'nullable|string|max:20000',
            'styles'        => 'nullable|string|max:2000',
            'page_number'   => 'nullable|integer|min:1|max:20',
            'sort_order'    => 'nullable|integer|min:0|max:9999',
        ];
    }

    /**
     * A new component is visible unless the switch is off; an edit takes the
     * switches at face value, so an unticked box really hides it - the same
     * convention as the other admin screens.
     *
     * @return array<string,mixed>
     */
    private function payload(Request $request, array $data, bool $isNew): array
    {
        $engine = new TemplateEngine();

        return [
            'name'          => (string) $data['name'],
            'type'          => (string) $data['type'],
            // Stored sanitised as well as sanitised on render: a stored
            // payload is never trusted just because an administrator typed it.
            'content'       => $engine->sanitiseHtml((string) ($data['content'] ?? '')),
            'styles'        => $this->parseStyles((string) ($data['styles'] ?? '')),
            'page_number'   => max(1, (int) ($data['page_number'] ?? 1)),
            'sort_order'    => (int) ($data['sort_order'] ?? 100),
            'is_visible'    => $request->bool('is_visible', $isNew) ? 1 : 0,
            'is_toggleable' => $request->bool('is_toggleable', $isNew) ? 1 : 0,
        ];
    }

    private function normaliseKey(string $key, string $fallback): string
    {
        $source = trim($key) !== '' ? $key : $fallback;
        $key = strtolower(preg_replace('/[^A-Za-z0-9_]/', '_', $source) ?? '');
        $key = trim(preg_replace('/_+/', '_', $key) ?? '', '_');
        return $key === '' ? 'component_' . substr(md5(uniqid('', true)), 0, 6) : mb_substr($key, 0, 64);
    }

    /**
     * `property: value` lines into a map the renderer can use. Anything that
     * is not a plain CSS property/value pair is dropped rather than escaped,
     * so nothing odd can reach the style attribute.
     *
     * @return array<string,string>
     */
    private function parseStyles(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $out = [];
        foreach (preg_split('/(?:\R|;)/u', $raw) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $line, 2));
            $property = strtolower(preg_replace('/[^a-z\-]/i', '', $property) ?? '');
            $value = trim(preg_replace('/[<>"\'{};]/', '', $value) ?? '');
            if ($property !== '' && $value !== '' && mb_strlen($value) <= 120) {
                $out[$property] = $value;
            }
        }
        return $out;
    }
}
