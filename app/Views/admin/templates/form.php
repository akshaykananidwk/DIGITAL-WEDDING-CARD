<?php
/**
 * @var array<string,mixed>|null $template
 * @var array<int,array<string,mixed>> $categories
 * @var array<string,string> $layouts
 * @var array<int,array<string,mixed>> $palettes
 * @var array<string,array<string,string>> $fontPairs
 * @var array<string,string> $fonts
 * @var array<string,string> $presets
 * @var array<string,string> $types
 */
$view->extend('layouts.admin');
$isNew = $template === null;
$action = $isNew ? url('admin/templates') : url('admin/templates/' . $template['id']);
$theme = is_array($template['theme'] ?? null) ? $template['theme'] : [];
$value = static fn (string $key, mixed $default = '') => old($key, (string) ($template[$key] ?? $default));
$tags = is_array($template['tags'] ?? null) ? implode(', ', $template['tags']) : '';
$flag = static fn (string $key, bool $default = false): bool
    => $template === null ? $default : (int) ($template[$key] ?? 0) === 1;
?>
<form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3">Basics</h2>
                <div class="row g-3">
                    <div class="col-12 col-sm-8">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control <?= error_for('name') ? 'is-invalid' : '' ?>" type="text" id="name"
                               name="name" required maxlength="150" value="<?= e($value('name')) ?>">
                        <?php if ($m = error_for('name')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="form-label" for="code">Code</label>
                        <input class="form-control" type="text" id="code" name="code" maxlength="40"
                               value="<?= e($value('code')) ?>" placeholder="auto">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"
                                  maxlength="2000"><?= e($value('description')) ?></textarea>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label" for="category_id">Category</label>
                        <select class="form-select <?= error_for('category_id') ? 'is-invalid' : '' ?>"
                                id="category_id" name="category_id" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"
                                    <?= (int) ($template['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= e($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($m = error_for('category_id')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label" for="subcategory_id">Subcategory</label>
                        <select class="form-select" id="subcategory_id" name="subcategory_id">
                            <option value="">None</option>
                            <?php foreach ($categories as $category): ?>
                                <optgroup label="<?= eattr($category['name']) ?>">
                                    <?php foreach ($category['subcategories'] as $sub): ?>
                                        <option value="<?= (int) $sub['id'] ?>"
                                            <?= (int) ($template['subcategory_id'] ?? 0) === (int) $sub['id'] ? 'selected' : '' ?>>
                                            <?= e($sub['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="form-label" for="type">Type</label>
                        <select class="form-select" id="type" name="type" required>
                            <?php foreach ($types as $key => $label): ?>
                                <option value="<?= e($key) ?>"
                                    <?= (string) ($template['type'] ?? 'kankotri') === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="form-label" for="layout_key">Layout renderer</label>
                        <select class="form-select" id="layout_key" name="layout_key" required>
                            <?php foreach ($layouts as $key => $label): ?>
                                <option value="<?= e($key) ?>"
                                    <?= (string) ($template['layout_key'] ?? '') === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-2">
                        <label class="form-label" for="language">Language</label>
                        <select class="form-select" id="language" name="language" required>
                            <?php foreach (['gu' => 'ગુજરાતી', 'hi' => 'हिन्दी', 'en' => 'English', 'multi' => 'Multi'] as $key => $label): ?>
                                <option value="<?= e($key) ?>"
                                    <?= (string) ($template['language'] ?? 'gu') === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-2">
                        <label class="form-label" for="page_count">Pages</label>
                        <input class="form-control" type="number" id="page_count" name="page_count" min="1" max="12"
                               value="<?= e($value('page_count', '1')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="tags">Tags</label>
                        <input class="form-control" type="text" id="tags" name="tags" maxlength="500"
                               value="<?= e(old('tags', $tags)) ?>" placeholder="krishna, royal, floral">
                        <div class="form-text">Comma separated. Used by search and the recommender.</div>
                    </div>
                </div>
            </section>

            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3">Design</h2>
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label" for="palette">Palette</label>
                        <select class="form-select" id="palette" name="palette">
                            <?php foreach ($palettes as $paletteKey => $palette): ?>
                                <option value="<?= e((string) $paletteKey) ?>"
                                    <?= (string) ($template['palette'] ?? 'kumkum-red') === (string) $paletteKey ? 'selected' : '' ?>>
                                    <?= e((string) $palette['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">The palette fills in every colour token. Overrides below win.</div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label" for="font_pair">Font pairing</label>
                        <select class="form-select" id="font_pair" name="font_pair">
                            <?php foreach ($fontPairs as $key => $pair): ?>
                                <option value="<?= e((string) $key) ?>"
                                    <?= (string) ($template['font_pair'] ?? 'script-sans') === (string) $key ? 'selected' : '' ?>>
                                    <?= e(ucwords(str_replace('-', ' ', (string) $key))) ?> · <?= e((string) $pair['heading']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php foreach ([
                        'color_primary'    => ['Primary', $theme['primary'] ?? '#C8102E'],
                        'color_secondary'  => ['Secondary', $theme['secondary'] ?? '#F0B429'],
                        'color_background' => ['Background', $theme['background'] ?? '#FFF8EE'],
                    ] as $field => [$label, $current]): ?>
                        <div class="col-12 col-sm-4">
                            <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                            <div class="input-group">
                                <input class="form-control form-control-color flex-grow-0" type="color"
                                       value="<?= e((string) $current) ?>" data-sk-color-sync="#<?= e($field) ?>"
                                       aria-label="<?= eattr($label) ?>">
                                <input class="form-control" type="text" id="<?= e($field) ?>" name="<?= e($field) ?>"
                                       pattern="#[0-9a-fA-F]{6}" value="<?= e((string) ($template[$field] ?? '')) ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-12 col-sm-4">
                        <label class="form-label" for="font_heading">Heading font</label>
                        <select class="form-select" id="font_heading" name="font_heading">
                            <option value="">From pairing</option>
                            <?php foreach ($fonts as $family => $label): ?>
                                <option value="<?= e((string) $family) ?>"
                                    <?= (string) ($template['font_heading'] ?? '') === (string) $family ? 'selected' : '' ?>>
                                    <?= e((string) $label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="form-label" for="font_body">Body font</label>
                        <select class="form-select" id="font_body" name="font_body">
                            <option value="">From pairing</option>
                            <?php foreach ($fonts as $family => $label): ?>
                                <option value="<?= e((string) $family) ?>"
                                    <?= (string) ($template['font_body'] ?? '') === (string) $family ? 'selected' : '' ?>>
                                    <?= e((string) $label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-2">
                        <label class="form-label" for="motion">Motion</label>
                        <select class="form-select" id="motion" name="motion">
                            <?php foreach (['gentle', 'rich', 'none'] as $motion): ?>
                                <option value="<?= e($motion) ?>"
                                    <?= (string) ($theme['motion'] ?? 'gentle') === $motion ? 'selected' : '' ?>>
                                    <?= e(ucfirst($motion)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-2">
                        <label class="form-label" for="ornament">Ornament</label>
                        <input class="form-control" type="text" id="ornament" name="ornament" maxlength="30"
                               value="<?= e((string) ($theme['ornament'] ?? '')) ?>" placeholder="paisley">
                    </div>
                </div>
            </section>

            <section class="sk-panel mb-4">
                <h2 class="h6 mb-1">Custom markup <span class="text-muted small">(optional)</span></h2>
                <p class="form-text mt-0">
                    Use <code>{{field_key}}</code> placeholders. HTML and CSS are sanitised on save and again on
                    render; scripts and event attributes are stripped.
                </p>
                <div class="mb-3">
                    <label class="form-label" for="custom_html">HTML</label>
                    <textarea class="form-control font-monospace" id="custom_html" name="custom_html" rows="6"
                              maxlength="120000"><?= e($value('custom_html')) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="custom_css">CSS</label>
                    <textarea class="form-control font-monospace" id="custom_css" name="custom_css" rows="5"
                              maxlength="60000"><?= e($value('custom_css')) ?></textarea>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-4">
            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3">Publishing</h2>
                <?php foreach ([
                    'is_active'   => ['Active (visible to users)', true],
                    'is_featured' => ['Featured on the home page', false],
                    'is_premium'  => ['Marked premium', false],
                ] as $field => [$label, $default]): ?>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="<?= e($field) ?>"
                               name="<?= e($field) ?>" <?= $flag($field, $default) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="<?= e($field) ?>"><?= e($label) ?></label>
                    </div>
                <?php endforeach; ?>

                <div class="mt-3">
                    <label class="form-label" for="sort_order">Sort order</label>
                    <input class="form-control" type="number" id="sort_order" name="sort_order" min="0" max="99999"
                           value="<?= e($value('sort_order', '100')) ?>">
                </div>
            </section>

            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3">Supported sections</h2>
                <?php foreach ([
                    'supports_countdown' => 'Countdown',
                    'supports_gallery'   => 'Photo gallery',
                    'supports_rsvp'      => 'RSVP',
                    'supports_map'       => 'Map',
                    'supports_music'     => 'Music',
                ] as $field => $label): ?>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="<?= e($field) ?>"
                               name="<?= e($field) ?>" <?= $flag($field, true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="<?= e($field) ?>"><?= e($label) ?></label>
                    </div>
                <?php endforeach; ?>
            </section>

            <?php if ($isNew): ?>
                <section class="sk-panel mb-4">
                    <h2 class="h6 mb-2">Field preset</h2>
                    <p class="form-text mt-0">The preset creates the editable fields for this occasion.</p>
                    <select class="form-select" id="field_preset" name="field_preset" aria-label="Field preset">
                        <?php foreach ($presets as $key => $label): ?>
                            <option value="<?= e((string) $key) ?>" <?= $key === 'wedding' ? 'selected' : '' ?>>
                                <?= e((string) $label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </section>
            <?php endif; ?>

            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3">SEO</h2>
                <div class="mb-3">
                    <label class="form-label" for="meta_title">Meta title</label>
                    <input class="form-control" type="text" id="meta_title" name="meta_title" maxlength="191"
                           value="<?= e($value('meta_title')) ?>">
                </div>
                <div>
                    <label class="form-label" for="meta_description">Meta description</label>
                    <textarea class="form-control" id="meta_description" name="meta_description" rows="3"
                              maxlength="300"><?= e($value('meta_description')) ?></textarea>
                </div>
            </section>

            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit"><?= $isNew ? 'Create template' : 'Save template' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/templates')) ?>">Cancel</a>
                <?php if (!$isNew): ?>
                    <a class="btn btn-outline-secondary" href="<?= e(url('admin/templates/' . $template['id'] . '/preview')) ?>"
                       target="_blank" rel="noopener">Preview</a>
                    <a class="btn btn-outline-secondary" href="<?= e(url('admin/templates/' . $template['id'] . '/fields')) ?>">
                        Fields
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<?php $view->start('scripts'); ?>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('js/builder.js')) ?>"></script>
<script<?= App\Core\Csp::attribute() ?>>
    window.addEventListener('load', function () { SK.builder.init({ invitationId: 0 }); });
</script>
<?php $view->stop(); ?>
