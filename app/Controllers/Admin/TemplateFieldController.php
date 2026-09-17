<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\TemplateFieldRepository;
use App\Repositories\TemplateRepository;
use App\Seeds\FieldPresets;
use App\Seeds\TemplateSeeder;
use App\Services\AuditService;

/**
 * Template fields.
 *
 * This is the screen that makes the platform extensible without code: define
 * a field here and the builder form, its validation, the live preview and the
 * API all pick it up.
 */
final class TemplateFieldController extends AdminController
{
    private const TYPES = [
        'text' => 'Text', 'textarea' => 'Long text', 'richtext' => 'Rich text',
        'date' => 'Date', 'time' => 'Time', 'datetime' => 'Date & time', 'number' => 'Number',
        'image' => 'Image', 'gallery' => 'Photo gallery', 'phone' => 'Phone', 'email' => 'Email',
        'url' => 'URL', 'location' => 'Google Maps link', 'color' => 'Colour', 'font' => 'Font',
        'select' => 'Dropdown', 'multiselect' => 'Multi-select', 'checkbox' => 'Checkbox',
        'social' => 'Social link', 'music' => 'Music',
    ];

    public function __construct(
        private readonly TemplateRepository $templates = new TemplateRepository(),
        private readonly TemplateFieldRepository $fields = new TemplateFieldRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        $template = $this->requireTemplate($request);

        return $this->admin('admin.templates.fields', 'Fields · ' . $template['name'], [
            'template' => $template,
            'fields'   => $this->fields->forTemplate((int) $template['id']),
            'types'    => self::TYPES,
            'sections' => FieldPresets::SECTIONS,
            'presets'  => FieldPresets::names(),
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

        $key = $this->normaliseKey((string) $data['field_key']);
        if ($this->fields->db()->first(
            'SELECT id FROM ' . $this->fields->db()->wrap($this->fields->db()->table('template_fields'))
            . ' WHERE template_id = :t AND field_key = :k',
            ['t' => (int) $template['id'], 'k' => $key]
        ) !== null) {
            return $this->respond(
                $request,
                false,
                'A field with the key "' . $key . '" already exists on this template.',
                'admin/templates/' . $template['id'] . '/fields'
            );
        }

        $id = $this->fields->create($this->payload($request, $data) + [
            'template_id' => (int) $template['id'],
            'field_key'   => $key,
        ]);

        $this->templates->flushDefinition((int) $template['id']);
        AuditService::instance()->log('admin.template.field_create', 'template_field', $id, $key);

        return $this->respond($request, true, 'Field added.', 'admin/templates/' . $template['id'] . '/fields');
    }

    public function update(Request $request): Response
    {
        $template = $this->requireTemplate($request);
        $field = $this->fields->find($request->int('fieldId'));
        if ($field === null || (int) $field['template_id'] !== (int) $template['id']) {
            throw HttpException::notFound('That field does not exist.');
        }

        try {
            $data = $this->validate($request, $this->rules());
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $this->fields->update((int) $field['id'], $this->payload($request, $data));
        $this->templates->flushDefinition((int) $template['id']);
        AuditService::instance()->log('admin.template.field_update', 'template_field', (int) $field['id'], (string) $field['field_key']);

        return $this->respond($request, true, 'Field updated.', 'admin/templates/' . $template['id'] . '/fields');
    }

    public function reorder(Request $request): Response
    {
        $template = $this->requireTemplate($request);
        $order = array_map('intval', $request->array('order'));
        if ($order !== []) {
            $this->fields->reorder((int) $template['id'], $order);
            $this->templates->flushDefinition((int) $template['id']);
        }
        return $this->respond($request, true, 'Order saved.', 'admin/templates/' . $template['id'] . '/fields');
    }

    public function destroy(Request $request): Response
    {
        $template = $this->requireTemplate($request);
        $field = $this->fields->find($request->int('fieldId'));
        if ($field === null || (int) $field['template_id'] !== (int) $template['id']) {
            throw HttpException::notFound();
        }

        $this->fields->forceDelete((int) $field['id']);
        $this->templates->flushDefinition((int) $template['id']);
        AuditService::instance()->log('admin.template.field_delete', 'template_field', (int) $field['id'], (string) $field['field_key']);

        return $this->respond($request, true, 'Field removed.', 'admin/templates/' . $template['id'] . '/fields');
    }

    /** Replace the field set with one of the presets. */
    public function applyPreset(Request $request): Response
    {
        $template = $this->requireTemplate($request);
        $preset = (string) $request->input('preset', '');
        if (!array_key_exists($preset, FieldPresets::names())) {
            return $this->respond($request, false, 'Unknown preset.', 'admin/templates/' . $template['id'] . '/fields');
        }

        $replace = $request->bool('replace');
        if ($replace) {
            $this->fields->deleteForTemplate((int) $template['id']);
        }

        $existing = [];
        foreach ($this->fields->forTemplate((int) $template['id']) as $field) {
            $existing[(string) $field['field_key']] = true;
        }

        $added = 0;
        $order = 0;
        foreach (FieldPresets::get($preset) as $definition) {
            $order += 10;
            $key = (string) $definition['field_key'];
            if (isset($existing[$key])) {
                continue;
            }
            $this->fields->create([
                'template_id'       => (int) $template['id'],
                'field_key'         => $key,
                'label'             => (string) $definition['label'],
                'label_gu'          => (string) $definition['label_gu'],
                'label_hi'          => (string) $definition['label_hi'],
                'type'              => (string) $definition['type'],
                'section'           => (string) $definition['section'],
                'placeholder'       => (string) $definition['placeholder'],
                'help_text'         => (string) $definition['help_text'],
                'max_length'        => $definition['max_length'],
                'is_required'       => !empty($definition['is_required']) ? 1 : 0,
                'is_editable'       => 1,
                'is_visible'        => 1,
                'is_ai_generatable' => !empty($definition['is_ai_generatable']) ? 1 : 0,
                'sort_order'        => $order,
            ]);
            $added++;
        }

        // The preset's demo values make the first preview look finished.
        $this->templates->update((int) $template['id'], [
            'demo_data' => FieldPresets::demoData($preset),
        ]);
        $this->templates->flushDefinition((int) $template['id']);
        AuditService::instance()->log('admin.template.field_preset', 'template', (int) $template['id'], $preset);

        return $this->respond(
            $request,
            true,
            $added . ' field(s) added from the "' . $preset . '" preset.',
            'admin/templates/' . $template['id'] . '/fields'
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

    /** @return array<string,string> */
    private function rules(): array
    {
        return [
            'field_key'   => 'required|string|max:64|alpha_dash',
            'label'       => 'required|string|max:160|no_html',
            'label_gu'    => 'nullable|string|max:191|no_html',
            'label_hi'    => 'nullable|string|max:191|no_html',
            'type'        => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'section'     => 'required|string|max:60|alpha_dash',
            'placeholder' => 'nullable|string|max:191|no_html',
            'help_text'   => 'nullable|string|max:300|no_html',
            'default_value' => 'nullable|string|max:2000|safe_text',
            'options'     => 'nullable|string|max:2000',
            'validation'  => 'nullable|string|max:191',
            'max_length'  => 'nullable|integer|min:1|max:100000',
            'sort_order'  => 'nullable|integer|min:0|max:9999',
        ];
    }

    /** @return array<string,mixed> */
    private function payload(Request $request, array $data): array
    {
        return [
            'label'        => (string) $data['label'],
            'label_gu'     => $data['label_gu'] ?? null,
            'label_hi'     => $data['label_hi'] ?? null,
            'type'         => (string) $data['type'],
            'section'      => (string) $data['section'],
            'placeholder'  => $data['placeholder'] ?? null,
            'help_text'    => $data['help_text'] ?? null,
            'default_value' => $data['default_value'] ?? null,
            'options'      => $this->parseOptions((string) ($data['options'] ?? '')),
            'validation'   => $this->safeValidation((string) ($data['validation'] ?? '')),
            'max_length'   => empty($data['max_length']) ? null : (int) $data['max_length'],
            'is_required'  => $request->bool('is_required') ? 1 : 0,
            'is_editable'  => $request->bool('is_editable', true) ? 1 : 0,
            'is_visible'   => $request->bool('is_visible', true) ? 1 : 0,
            'is_ai_generatable' => $request->bool('is_ai_generatable') ? 1 : 0,
            'sort_order'   => (int) ($data['sort_order'] ?? 100),
        ];
    }

    private function normaliseKey(string $key): string
    {
        $key = strtolower(preg_replace('/[^A-Za-z0-9_]/', '_', $key) ?? '');
        $key = trim(preg_replace('/_+/', '_', $key) ?? '', '_');
        return $key === '' ? 'field_' . substr(md5(uniqid('', true)), 0, 6) : mb_substr($key, 0, 64);
    }

    /** @return array<int,array{value:string,label:string}> */
    private function parseOptions(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $out = [];
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (str_contains($line, '|')) {
                [$value, $label] = array_map('trim', explode('|', $line, 2));
            } else {
                $value = $label = $line;
            }
            $value = preg_replace('/[^A-Za-z0-9_\-]/', '', $value) ?? '';
            if ($value === '') {
                continue;
            }
            $out[] = ['value' => $value, 'label' => mb_substr($label, 0, 120)];
        }
        return $out;
    }

    /**
     * Only allow validation rules the validator actually understands, so an
     * admin cannot accidentally (or deliberately) inject something odd into
     * the rule string.
     */
    private function safeValidation(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $allowed = [
            'string', 'integer', 'numeric', 'boolean', 'email', 'url', 'phone',
            'date', 'time', 'datetime', 'after_today', 'slug', 'alpha_dash',
            'hex_color', 'no_html', 'safe_text', 'json',
        ];
        $kept = [];
        foreach (explode('|', $raw) as $rule) {
            $rule = trim($rule);
            $name = explode(':', $rule)[0];
            if (in_array($name, $allowed, true)) {
                $kept[] = $rule;
                continue;
            }
            if (in_array($name, ['min', 'max', 'between', 'in'], true)
                && preg_match('/^[a-z]+:[A-Za-z0-9,_\- ]+$/', $rule) === 1) {
                $kept[] = $rule;
            }
        }
        return $kept === [] ? null : mb_substr(implode('|', $kept), 0, 191);
    }
}
