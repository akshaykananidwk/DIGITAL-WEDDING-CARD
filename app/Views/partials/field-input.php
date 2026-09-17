<?php
/**
 * Renders one template field definition as a form control.
 *
 * Every field type in the template_fields enum is handled here, which is
 * what lets a new template introduce new inputs without any new code.
 *
 * @var array<string,mixed> $field
 * @var array<string,mixed> $values
 * @var bool $aiEnabled
 */

use App\Core\Lang;

$key = (string) $field['field_key'];
$type = (string) $field['type'];
$id = 'f-' . preg_replace('/[^a-z0-9_\-]/i', '', $key);
$locale = Lang::locale();
$label = match ($locale) {
    'gu' => (string) ($field['label_gu'] ?: $field['label']),
    'hi' => (string) ($field['label_hi'] ?: $field['label']),
    default => (string) $field['label'],
};
$required = (int) $field['is_required'] === 1;
$readonly = (int) $field['is_editable'] !== 1;
$raw = $values[$key] ?? ($field['default_value'] ?? '');
$value = is_array($raw) ? implode(', ', array_map('strval', $raw)) : (string) $raw;
$options = is_array($field['options'] ?? null) ? $field['options'] : [];
$max = $field['max_length'] === null ? null : (int) $field['max_length'];
$placeholder = (string) ($field['placeholder'] ?? '');
$help = (string) ($field['help_text'] ?? '');
$aiable = $aiEnabled && (int) ($field['is_ai_generatable'] ?? 0) === 1;
$invalid = error_for('fields.' . $key) ?? error_for($key);

$attributes = 'id="' . eattr($id) . '" name="fields[' . eattr($key) . ']" data-sk-field="' . eattr($key) . '"'
    . ($required ? ' required' : '')
    . ($readonly ? ' readonly' : '')
    . ($max !== null ? ' maxlength="' . $max . '"' : '')
    . ($placeholder !== '' ? ' placeholder="' . eattr($placeholder) . '"' : '');
$class = 'form-control' . ($invalid !== null ? ' is-invalid' : '');
?>
<div class="sk-field" data-type="<?= e($type) ?>">
    <div class="d-flex align-items-center justify-content-between gap-2">
        <label class="form-label mb-1" for="<?= e($id) ?>">
            <?= e($label) ?>
            <?php if ($required): ?><span class="text-danger" aria-hidden="true">*</span><?php endif; ?>
        </label>
        <?php if ($aiable): ?>
            <button class="btn btn-sm btn-link p-0 text-decoration-none" type="button"
                    data-sk-ai="text" data-sk-ai-target="#<?= e($id) ?>"
                    title="<?= eattr(__('builder.ai_help')) ?>">
                <i class="bi bi-stars" aria-hidden="true"></i>
                <span class="d-none d-sm-inline small"><?= e(__('builder.ai_help')) ?></span>
            </button>
        <?php endif; ?>
    </div>

    <?php switch ($type):
        case 'textarea': ?>
            <textarea class="<?= e($class) ?>" rows="3" <?= $attributes ?>><?= e($value) ?></textarea>
            <?php break;

        case 'richtext': ?>
            <textarea class="<?= e($class) ?>" rows="5" <?= $attributes ?>><?= e($value) ?></textarea>
            <?php break;

        case 'date': ?>
            <input class="<?= e($class) ?>" type="date" value="<?= e($value) ?>" <?= $attributes ?>>
            <?php break;

        case 'time': ?>
            <input class="<?= e($class) ?>" type="time" value="<?= e($value) ?>" <?= $attributes ?>>
            <?php break;

        case 'datetime': ?>
            <input class="<?= e($class) ?>" type="datetime-local"
                   value="<?= e(str_replace(' ', 'T', substr($value, 0, 16))) ?>" <?= $attributes ?>>
            <?php break;

        case 'number': ?>
            <input class="<?= e($class) ?>" type="number" inputmode="numeric"
                   value="<?= e($value) ?>" <?= $attributes ?>>
            <?php break;

        case 'phone': ?>
            <input class="<?= e($class) ?>" type="tel" inputmode="tel" value="<?= e($value) ?>" <?= $attributes ?>>
            <?php break;

        case 'email': ?>
            <input class="<?= e($class) ?>" type="email" inputmode="email" value="<?= e($value) ?>" <?= $attributes ?>>
            <?php break;

        case 'url':
        case 'social': ?>
            <input class="<?= e($class) ?>" type="url" inputmode="url"
                   value="<?= e($value) ?>" <?= $attributes ?>>
            <?php break;

        case 'location': ?>
            <div class="input-group">
                <input class="<?= e($class) ?>" type="url" inputmode="url" value="<?= e($value) ?>" <?= $attributes ?>>
                <?php if ($value !== ''): ?>
                    <a class="btn btn-outline-secondary" href="<?= e($value) ?>" target="_blank" rel="noopener noreferrer"
                       aria-label="<?= eattr(__('invite.get_directions')) ?>">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php break;

        case 'color': ?>
            <div class="input-group">
                <input class="form-control form-control-color flex-grow-0" type="color"
                       value="<?= e($value !== '' ? $value : '#C8102E') ?>"
                       data-sk-color-sync="#<?= e($id) ?>" aria-label="<?= eattr($label) ?>">
                <input class="<?= e($class) ?>" type="text" value="<?= e($value) ?>"
                       pattern="#[0-9a-fA-F]{6}" <?= $attributes ?>>
            </div>
            <?php break;

        case 'select': ?>
            <select class="form-select<?= $invalid !== null ? ' is-invalid' : '' ?>" <?= $attributes ?>>
                <?php if (!$required): ?><option value=""><?= e(__('common.none')) ?></option><?php endif; ?>
                <?php foreach ($options as $optionValue => $optionLabel): ?>
                    <?php $optionValue = is_int($optionValue) ? (string) $optionLabel : (string) $optionValue; ?>
                    <option value="<?= e($optionValue) ?>" <?= $value === $optionValue ? 'selected' : '' ?>>
                        <?= e((string) $optionLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php break;

        case 'multiselect': ?>
            <?php $selected = array_map('trim', explode(',', $value)); ?>
            <div class="sk-checks<?= $invalid !== null ? ' is-invalid' : '' ?>">
                <?php foreach ($options as $optionValue => $optionLabel): ?>
                    <?php
                    $optionValue = is_int($optionValue) ? (string) $optionLabel : (string) $optionValue;
                    $optionId = $id . '-' . preg_replace('/[^a-z0-9]/i', '', $optionValue);
                    ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="<?= e($optionId) ?>"
                               name="fields[<?= eattr($key) ?>][]" value="<?= eattr($optionValue) ?>"
                               <?= in_array($optionValue, $selected, true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="<?= e($optionId) ?>"><?= e((string) $optionLabel) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php break;

        case 'checkbox': ?>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" value="1"
                       id="<?= e($id) ?>" name="fields[<?= eattr($key) ?>]" data-sk-field="<?= eattr($key) ?>"
                       <?= in_array($value, ['1', 'true', 'on', 'yes'], true) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="<?= e($id) ?>"><?= e($help !== '' ? $help : $label) ?></label>
            </div>
            <?php break;

        case 'font': ?>
            <select class="form-select<?= $invalid !== null ? ' is-invalid' : '' ?>" <?= $attributes ?>>
                <option value=""><?= e(__('common.none')) ?></option>
                <?php foreach (($fonts ?? []) as $family => $fontLabel): ?>
                    <option value="<?= e((string) $family) ?>" <?= $value === (string) $family ? 'selected' : '' ?>>
                        <?= e((string) $fontLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php break;

        case 'image':
        case 'gallery':
        case 'music': ?>
            <p class="form-text mb-0">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                <?= e(__('builder.media_step_hint')) ?>
            </p>
            <?php break;

        default: ?>
            <input class="<?= e($class) ?>" type="text" value="<?= e($value) ?>" <?= $attributes ?>>
    <?php endswitch; ?>

    <?php if ($help !== '' && $type !== 'checkbox'): ?>
        <div class="form-text"><?= e($help) ?></div>
    <?php endif; ?>
    <div class="invalid-feedback d-block" data-sk-error-for="<?= eattr($key) ?>"><?= e((string) $invalid) ?></div>
</div>
