<?php
/**
 * The component builder: the second way to author a template, for a bespoke
 * card whose blocks and page order are decided here rather than in PHP.
 *
 * @var array<string,mixed> $template
 * @var array<int,array<string,mixed>> $components
 * @var array<int,array<int,array<string,mixed>>> $pages
 * @var array<string,string> $types
 * @var array<int,string> $fieldKeys
 */
$view->extend('layouts.admin');
$templateId = (int) $template['id'];
$styleText = static function (mixed $styles): string {
    if (!is_array($styles)) {
        return '';
    }
    $lines = [];
    foreach ($styles as $property => $value) {
        if (is_string($property) && is_scalar($value)) {
            $lines[] = $property . ': ' . $value;
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
    <span class="text-muted">Components</span>
</nav>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <section class="sk-panel">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h6 mb-0">
                    <?= count($components) ?> component<?= count($components) === 1 ? '' : 's' ?>
                    <?php if ($pages !== []): ?>
                        <span class="text-muted fw-normal">· <?= count($pages) ?> page<?= count($pages) === 1 ? '' : 's' ?></span>
                    <?php endif; ?>
                </h2>
                <span class="small text-muted">Drag to reorder</span>
            </div>

            <?php if ($components === []): ?>
                <p class="text-muted small mb-0">
                    No components. This template renders through its layout
                    (<code class="sk-code"><?= e((string) $template['layout_key']) ?></code>).
                    Add one below to build the card from blocks instead — the
                    layout is then bypassed.
                </p>
            <?php else: ?>
                <p class="form-text mt-0 mb-3">
                    While this list has a visible component with content, the
                    invitation renders from these blocks and not from the
                    layout. Several page numbers become a page-turning card.
                </p>
                <div id="sk-component-list" class="accordion">
                    <?php foreach ($components as $component): ?>
                        <?php $cid = (int) $component['id']; ?>
                        <div class="accordion-item" data-id="<?= $cid ?>">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#component-<?= $cid ?>" aria-expanded="false">
                                    <i class="bi bi-grip-vertical me-2 text-muted" aria-hidden="true"></i>
                                    <span class="fw-semibold me-2"><?= e((string) $component['name']) ?></span>
                                    <code class="sk-code me-2"><?= e((string) $component['component_key']) ?></code>
                                    <span class="badge text-bg-light text-dark me-1">
                                        <?= e($types[(string) $component['type']] ?? (string) $component['type']) ?>
                                    </span>
                                    <span class="badge text-bg-light text-dark me-1">page <?= (int) $component['page_number'] ?></span>
                                    <?php if ((int) $component['is_visible'] !== 1): ?>
                                        <span class="badge text-bg-secondary">hidden</span>
                                    <?php endif; ?>
                                </button>
                            </h3>
                            <div class="accordion-collapse collapse" id="component-<?= $cid ?>">
                                <div class="accordion-body">
                                    <form method="post"
                                          action="<?= e(url('admin/templates/' . $templateId . '/components/' . $cid)) ?>">
                                        <?= csrf_field() ?>
                                        <div class="row g-2">
                                            <div class="col-12 col-sm-6">
                                                <label class="form-label small mb-1" for="n-<?= $cid ?>">Name</label>
                                                <input class="form-control form-control-sm" type="text" id="n-<?= $cid ?>"
                                                       name="name" required maxlength="120"
                                                       value="<?= e((string) $component['name']) ?>">
                                            </div>
                                            <div class="col-6 col-sm-3">
                                                <label class="form-label small mb-1" for="t-<?= $cid ?>">Type</label>
                                                <select class="form-select form-select-sm" id="t-<?= $cid ?>" name="type">
                                                    <?php foreach ($types as $key => $label): ?>
                                                        <option value="<?= e($key) ?>"
                                                            <?= (string) $component['type'] === $key ? 'selected' : '' ?>>
                                                            <?= e($label) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-3 col-sm-2">
                                                <label class="form-label small mb-1" for="p-<?= $cid ?>">Page</label>
                                                <input class="form-control form-control-sm" type="number" id="p-<?= $cid ?>"
                                                       name="page_number" min="1" max="20"
                                                       value="<?= (int) $component['page_number'] ?>">
                                            </div>
                                            <div class="col-3 col-sm-1">
                                                <label class="form-label small mb-1" for="o-<?= $cid ?>">Order</label>
                                                <input class="form-control form-control-sm" type="number" id="o-<?= $cid ?>"
                                                       name="sort_order" min="0" max="9999"
                                                       value="<?= (int) $component['sort_order'] ?>">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small mb-1" for="c-<?= $cid ?>">Content</label>
                                                <textarea class="form-control form-control-sm" id="c-<?= $cid ?>"
                                                          name="content" rows="5" maxlength="20000"><?= e((string) $component['content']) ?></textarea>
                                                <div class="form-text">
                                                    Markup with <code>{{field_key}}</code> placeholders. Scripts,
                                                    iframes and event handlers are stripped on save and again on render.
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small mb-1" for="s-<?= $cid ?>">Styles</label>
                                                <textarea class="form-control form-control-sm" id="s-<?= $cid ?>"
                                                          name="styles" rows="3" maxlength="2000"
                                                          placeholder="text-align: center"><?= e($styleText($component['styles'])) ?></textarea>
                                                <div class="form-text">One <code>property: value</code> per line.</div>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-3 mt-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" value="1"
                                                       id="v-<?= $cid ?>" name="is_visible"
                                                    <?= (int) $component['is_visible'] === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="v-<?= $cid ?>">Visible</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" value="1"
                                                       id="g-<?= $cid ?>" name="is_toggleable"
                                                    <?= (int) $component['is_toggleable'] === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="g-<?= $cid ?>">Owner may hide it</label>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 mt-3">
                                            <button class="btn btn-sm btn-primary" type="submit">Save component</button>
                                            <a class="btn btn-sm btn-outline-secondary"
                                               href="<?= e(url('admin/templates/' . $templateId . '/preview')) ?>"
                                               target="_blank" rel="noopener">Preview</a>
                                        </div>
                                    </form>

                                    <form class="mt-2" method="post"
                                          action="<?= e(url('admin/templates/' . $templateId . '/components/' . $cid)) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"
                                                data-sk-confirm="Remove this component?">
                                            Remove component
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
            <h2 class="h6 mb-3">Add a component</h2>
            <form method="post" action="<?= e(url('admin/templates/' . $templateId . '/components')) ?>">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="new-name">Name</label>
                    <input class="form-control form-control-sm <?= error_for('name') ? 'is-invalid' : '' ?>"
                           type="text" id="new-name" name="name" required maxlength="120"
                           placeholder="Opening blessing">
                    <?php if ($m = error_for('name')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small mb-1" for="new-type">Type</label>
                        <select class="form-select form-select-sm" id="new-type" name="type">
                            <?php foreach ($types as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $key === 'section' ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label small mb-1" for="new-page">Page</label>
                        <input class="form-control form-control-sm" type="number" id="new-page"
                               name="page_number" min="1" max="20" value="1">
                    </div>
                    <div class="col-3">
                        <label class="form-label small mb-1" for="new-order">Order</label>
                        <input class="form-control form-control-sm" type="number" id="new-order"
                               name="sort_order" min="0" max="9999" value="100">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="new-content">Content</label>
                    <textarea class="form-control form-control-sm" id="new-content" name="content" rows="4"
                              maxlength="20000" placeholder="&lt;p class=&quot;inv-lead&quot;&gt;{{invocation}}&lt;/p&gt;"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1" for="new-styles">Styles</label>
                    <textarea class="form-control form-control-sm" id="new-styles" name="styles" rows="2"
                              maxlength="2000" placeholder="text-align: center"></textarea>
                </div>
                <button class="btn btn-sm btn-primary w-100" type="submit">Add component</button>
            </form>
        </section>

        <section class="sk-panel">
            <h2 class="h6 mb-2">Placeholders</h2>
            <?php if ($fieldKeys === []): ?>
                <p class="text-muted small mb-0">
                    This template has no fields yet.
                    <a href="<?= e(url('admin/templates/' . $templateId . '/fields')) ?>">Add some</a>
                    and their keys appear here.
                </p>
            <?php else: ?>
                <p class="form-text mt-0">Use these in the content boxes.</p>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($fieldKeys as $key): ?>
                        <code class="sk-code">{{<?= e($key) ?>}}</code>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php $view->start('scripts'); ?>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('vendor/sortable.min.js')) ?>"></script>
<script<?= App\Core\Csp::attribute() ?>>
    window.addEventListener('load', function () {
        const list = document.getElementById('sk-component-list');
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
                const result = await SK.request('admin/templates/<?= $templateId ?>/components/reorder', {
                    method: 'POST', body: body,
                });
                SK.toast(result.message || 'Order saved', result.success ? 'success' : 'danger', 1800);
            },
        });
    });
</script>
<?php $view->stop(); ?>
