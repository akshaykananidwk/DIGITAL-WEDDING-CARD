<?php
/**
 * The admin field builder: this is what lets one layout serve thousands of
 * templates - the fields are data, not code.
 *
 * @var array<string,mixed> $template
 * @var array<int,array<string,mixed>> $fields
 * @var array<string,string> $types
 * @var array<string,string> $sections
 * @var array<string,string> $presets
 */
$view->extend('layouts.admin');
$templateId = (int) $template['id'];
$optionText = static function (mixed $options): string {
    if (!is_array($options)) {
        return '';
    }
    $lines = [];
    foreach ($options as $option) {
        if (is_array($option)) {
            $lines[] = ($option['value'] ?? '') . '|' . ($option['label'] ?? '');
        } else {
            $lines[] = (string) $option;
        }
    }
    return implode("\n", $lines);
};
?>
<nav class="small mb-3">
    <a href="<?= e(url('admin/templates')) ?>">Templates</a>
    <span aria-hidden="true">/</span>
    <a href="<?= e(url('admin/templates/' . $templateId . '/edit')) ?>"><?= e($template['name']) ?></a>
    <span aria-hidden="true">/</span>
    <span class="text-muted">Fields</span>
</nav>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <section class="sk-panel">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h6 mb-0"><?= count($fields) ?> fields</h2>
                <span class="small text-muted">Drag to reorder</span>
            </div>

            <?php if ($fields === []): ?>
                <p class="text-muted small mb-0">
                    No fields yet. Apply a preset on the right, or add one field at a time.
                </p>
            <?php else: ?>
                <div id="sk-field-list" class="accordion">
                    <?php foreach ($fields as $field): ?>
                        <?php $fid = (int) $field['id']; ?>
                        <div class="accordion-item" data-id="<?= $fid ?>">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#field-<?= $fid ?>" aria-expanded="false">
                                    <i class="bi bi-grip-vertical me-2 text-muted" aria-hidden="true"></i>
                                    <span class="fw-semibold me-2"><?= e($field['label']) ?></span>
                                    <code class="sk-code me-2"><?= e($field['field_key']) ?></code>
                                    <span class="badge text-bg-light text-dark me-1"><?= e($types[(string) $field['type']] ?? $field['type']) ?></span>
                                    <span class="badge text-bg-light text-dark"><?= e($sections[(string) $field['section']] ?? $field['section']) ?></span>
                                    <?php if ((int) $field['is_required'] === 1): ?>
                                        <span class="sk-required ms-2" aria-label="Required">*</span>
                                    <?php endif; ?>
                                </button>
                            </h3>
                            <div class="accordion-collapse collapse" id="field-<?= $fid ?>">
                                <div class="accordion-body">
                                    <form method="post"
                                          action="<?= e(url('admin/templates/' . $templateId . '/fields/' . $fid)) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="field_key" value="<?= e($field['field_key']) ?>">
                                        <div class="row g-2">
                                            <div class="col-12 col-sm-6">
                                                <label class="form-label small mb-1" for="l-<?= $fid ?>">Label (English)</label>
                                                <input class="form-control form-control-sm" type="text" id="l-<?= $fid ?>"
                                                       name="label" required maxlength="160" value="<?= e($field['label']) ?>">
                                            </div>
                                            <div class="col-6 col-sm-3">
                                                <label class="form-label small mb-1" for="lg-<?= $fid ?>">ગુજરાતી</label>
                                                <input class="form-control form-control-sm" type="text" id="lg-<?= $fid ?>"
                                                       name="label_gu" maxlength="191" value="<?= e((string) $field['label_gu']) ?>">
                                            </div>
                                            <div class="col-6 col-sm-3">
                                                <label class="form-label small mb-1" for="lh-<?= $fid ?>">हिन्दी</label>
                                                <input class="form-control form-control-sm" type="text" id="lh-<?= $fid ?>"
                                                       name="label_hi" maxlength="191" value="<?= e((string) $field['label_hi']) ?>">
                                            </div>
                                            <div class="col-6 col-sm-4">
                                                <label class="form-label small mb-1" for="t-<?= $fid ?>">Type</label>
                                                <select class="form-select form-select-sm" id="t-<?= $fid ?>" name="type">
                                                    <?php foreach ($types as $key => $label): ?>
                                                        <option value="<?= e($key) ?>"
                                                            <?= (string) $field['type'] === $key ? 'selected' : '' ?>>
                                                            <?= e($label) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-6 col-sm-4">
                                                <label class="form-label small mb-1" for="s-<?= $fid ?>">Section</label>
                                                <select class="form-select form-select-sm" id="s-<?= $fid ?>" name="section">
                                                    <?php foreach ($sections as $key => $label): ?>
                                                        <option value="<?= e($key) ?>"
                                                            <?= (string) $field['section'] === $key ? 'selected' : '' ?>>
                                                            <?= e($label) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-6 col-sm-2">
                                                <label class="form-label small mb-1" for="m-<?= $fid ?>">Max length</label>
                                                <input class="form-control form-control-sm" type="number" id="m-<?= $fid ?>"
                                                       name="max_length" min="1" max="100000"
                                                       value="<?= e((string) ($field['max_length'] ?? '')) ?>">
                                            </div>
                                            <div class="col-6 col-sm-2">
                                                <label class="form-label small mb-1" for="o-<?= $fid ?>">Order</label>
                                                <input class="form-control form-control-sm" type="number" id="o-<?= $fid ?>"
                                                       name="sort_order" min="0" max="9999" value="<?= (int) $field['sort_order'] ?>">
                                            </div>
                                            <div class="col-12 col-sm-6">
                                                <label class="form-label small mb-1" for="p-<?= $fid ?>">Placeholder</label>
                                                <input class="form-control form-control-sm" type="text" id="p-<?= $fid ?>"
                                                       name="placeholder" maxlength="191"
                                                       value="<?= e((string) $field['placeholder']) ?>">
                                            </div>
                                            <div class="col-12 col-sm-6">
                                                <label class="form-label small mb-1" for="h-<?= $fid ?>">Help text</label>
                                                <input class="form-control form-control-sm" type="text" id="h-<?= $fid ?>"
                                                       name="help_text" maxlength="300"
                                                       value="<?= e((string) $field['help_text']) ?>">
                                            </div>
                                            <div class="col-12 col-sm-6">
                                                <label class="form-label small mb-1" for="d-<?= $fid ?>">Default value</label>
                                                <input class="form-control form-control-sm" type="text" id="d-<?= $fid ?>"
                                                       name="default_value" maxlength="2000"
                                                       value="<?= e((string) $field['default_value']) ?>">
                                            </div>
                                            <div class="col-12 col-sm-6">
                                                <label class="form-label small mb-1" for="opt-<?= $fid ?>">
                                                    Options <span class="text-muted">(value|label per line)</span>
                                                </label>
                                                <textarea class="form-control form-control-sm" id="opt-<?= $fid ?>"
                                                          name="options" rows="2" maxlength="2000"><?= e($optionText($field['options'] ?? null)) ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex flex-wrap gap-3">
                                                    <?php foreach ([
                                                        'is_required' => ['Required', (int) $field['is_required'] === 1],
                                                        'is_editable' => ['Editable by users', (int) $field['is_editable'] === 1],
                                                        'is_visible'  => ['Visible on the card', (int) $field['is_visible'] === 1],
                                                        'is_ai_generatable' => ['AI may draft it', (int) $field['is_ai_generatable'] === 1],
                                                    ] as $name => [$label, $checked]): ?>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" value="1"
                                                                   id="<?= e($name) ?>-<?= $fid ?>" name="<?= e($name) ?>"
                                                                <?= $checked ? 'checked' : '' ?>>
                                                            <label class="form-check-label small" for="<?= e($name) ?>-<?= $fid ?>">
                                                                <?= e($label) ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 mt-3">
                                            <button class="btn btn-sm btn-primary" type="submit">Save field</button>
                                        </div>
                                    </form>

                                    <form class="mt-2" method="post"
                                          action="<?= e(url('admin/templates/' . $templateId . '/fields/' . $fid)) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"
                                                data-sk-confirm="Remove this field? Saved values stay in the database.">
                                            Remove field
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="col-12 col-xl-4">
        <section class="sk-panel mb-4">
            <h2 class="h6 mb-2">Apply a preset</h2>
            <p class="form-text mt-0">Presets add the standard fields for an occasion.</p>
            <form method="post" action="<?= e(url('admin/templates/' . $templateId . '/fields/preset')) ?>">
                <?= csrf_field() ?>
                <select class="form-select form-select-sm mb-2" name="preset" aria-label="Preset">
                    <?php foreach ($presets as $key => $label): ?>
                        <option value="<?= e((string) $key) ?>"><?= e((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="replace" name="replace">
                    <label class="form-check-label small" for="replace">Replace the existing fields</label>
                </div>
                <button class="btn btn-sm btn-outline-primary w-100" type="submit"
                        data-sk-confirm="Apply this preset to the template?">Apply preset</button>
            </form>
        </section>

        <section class="sk-panel">
            <h2 class="h6 mb-3">Add a field</h2>
            <form method="post" action="<?= e(url('admin/templates/' . $templateId . '/fields')) ?>">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="new-key">Field key</label>
                    <input class="form-control form-control-sm <?= error_for('field_key') ? 'is-invalid' : '' ?>"
                           type="text" id="new-key" name="field_key" required maxlength="64"
                           pattern="[A-Za-z0-9_\-]+" placeholder="groom_name">
                    <?php if ($m = error_for('field_key')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                    <div class="form-text">Used in <code>{{placeholders}}</code> and in the API.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="new-label">Label</label>
                    <input class="form-control form-control-sm <?= error_for('label') ? 'is-invalid' : '' ?>"
                           type="text" id="new-label" name="label" required maxlength="160">
                    <?php if ($m = error_for('label')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small mb-1" for="new-type">Type</label>
                        <select class="form-select form-select-sm" id="new-type" name="type">
                            <?php foreach ($types as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1" for="new-section">Section</label>
                        <select class="form-select form-select-sm" id="new-section" name="section">
                            <?php foreach ($sections as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" value="1" id="new-required" name="is_required">
                    <label class="form-check-label small" for="new-required">Required</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="new-ai" name="is_ai_generatable">
                    <label class="form-check-label small" for="new-ai">AI may draft it</label>
                </div>
                <button class="btn btn-sm btn-primary w-100" type="submit">Add field</button>
            </form>
        </section>
    </div>
</div>

<?php $view->start('scripts'); ?>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('vendor/sortable.min.js')) ?>"></script>
<script<?= App\Core\Csp::attribute() ?>>
    window.addEventListener('load', function () {
        const list = document.getElementById('sk-field-list');
        if (!list || typeof window.Sortable === 'undefined') { return; }
        window.Sortable.create(list, {
            animation: 150,
            handle: '.accordion-button',
            onEnd: async function () {
                const body = new FormData();
                body.append('_token', SK.config.csrfToken);
                list.querySelectorAll('.accordion-item').forEach(function (item) {
                    body.append('order[]', item.getAttribute('data-id'));
                });
                const result = await SK.request('admin/templates/<?= $templateId ?>/fields/reorder', {
                    method: 'POST', body: body,
                });
                SK.toast(result.message || 'Order saved', result.success ? 'success' : 'danger', 1800);
            },
        });
    });
</script>
<?php $view->stop(); ?>
