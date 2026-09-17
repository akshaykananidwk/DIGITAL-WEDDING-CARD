<?php
/**
 * @var array<int,array<string,mixed>> $templates
 * @var array<string,mixed> $pagination
 * @var array<string,mixed> $filters
 * @var array<int,array<string,mixed>> $categories
 * @var array<int,string> $palette
 * @var int $total
 */
$view->extend('layouts.app');

$activeCategory = null;
foreach ($categories as $category) {
    if ((string) $category['slug'] === (string) $filters['category_slug']) {
        $activeCategory = $category;
    }
}
$hasFilters = ($filters['q'] ?? '') !== '' || ($filters['category_slug'] ?? '') !== ''
    || ($filters['type'] ?? '') !== '' || ($filters['language'] ?? '') !== ''
    || ($filters['animated'] ?? '') !== '' || ($filters['premium'] ?? '') !== ''
    || ($filters['color'] ?? '') !== '' || ($filters['tag'] ?? '') !== '';
?>

<div class="container py-4">
    <nav aria-label="Breadcrumb" class="small mb-2">
        <a href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a>
        <span aria-hidden="true">/</span>
        <span class="text-muted"><?= e(__('templates.title')) ?></span>
    </nav>

    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?= e(__('templates.title')) ?></h1>
            <p class="text-muted mb-0 small"><?= e(__('templates.results', ['count' => number_format($total)])) ?></p>
        </div>
        <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button"
                data-bs-toggle="collapse" data-bs-target="#sk-filters" aria-expanded="false" aria-controls="sk-filters">
            <i class="bi bi-funnel me-1" aria-hidden="true"></i><?= e(__('templates.filters')) ?>
        </button>
    </div>

    <div class="row g-4">
        <aside class="col-12 col-lg-3">
            <form class="collapse d-lg-block sk-filters" id="sk-filters" method="get" action="<?= e(url('templates')) ?>">
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="f-q"><?= e(__('templates.search')) ?></label>
                    <input class="form-control form-control-sm" type="search" id="f-q" name="q"
                           maxlength="80" value="<?= e((string) $filters['q']) ?>"
                           list="sk-q-suggestions" autocomplete="off">
                    <datalist id="sk-q-suggestions"></datalist>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="f-category"><?= e(__('nav.categories')) ?></label>
                    <select class="form-select form-select-sm" id="f-category" name="category"
                            data-sk-auto-submit>
                        <option value=""><?= e(__('common.all')) ?></option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category['slug']) ?>"
                                <?= (string) $filters['category_slug'] === (string) $category['slug'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($activeCategory !== null && $activeCategory['subcategories'] !== []): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="f-sub">&nbsp;</label>
                        <select class="form-select form-select-sm" id="f-sub" name="subcategory" data-sk-auto-submit>
                            <option value=""><?= e(__('common.all')) ?></option>
                            <?php foreach ($activeCategory['subcategories'] as $sub): ?>
                                <option value="<?= e($sub['slug']) ?>"
                                    <?= (string) $filters['subcategory_slug'] === (string) $sub['slug'] ? 'selected' : '' ?>>
                                    <?= e($sub['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="f-language"><?= e(__('common.language')) ?></label>
                    <select class="form-select form-select-sm" id="f-language" name="language" data-sk-auto-submit>
                        <option value=""><?= e(__('templates.all_languages')) ?></option>
                        <option value="gu" <?= $filters['language'] === 'gu' ? 'selected' : '' ?>>ગુજરાતી</option>
                        <option value="hi" <?= $filters['language'] === 'hi' ? 'selected' : '' ?>>हिन्दी</option>
                        <option value="en" <?= $filters['language'] === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="f-sort"><?= e(__('templates.sort')) ?></label>
                    <select class="form-select form-select-sm" id="f-sort" name="sort" data-sk-auto-submit>
                        <option value="popular" <?= $filters['sort'] === 'popular' ? 'selected' : '' ?>><?= e(__('templates.sort_popular')) ?></option>
                        <option value="latest" <?= $filters['sort'] === 'latest' ? 'selected' : '' ?>><?= e(__('templates.sort_latest')) ?></option>
                        <option value="name" <?= $filters['sort'] === 'name' ? 'selected' : '' ?>><?= e(__('templates.sort_name')) ?></option>
                    </select>
                </div>

                <fieldset class="mb-3">
                    <legend class="form-label small fw-semibold"><?= e(__('templates.filters')) ?></legend>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="f-animated" name="animated" value="1"
                               data-sk-auto-submit <?= $filters['animated'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="f-animated"><?= e(__('templates.animated')) ?></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="f-free" name="premium" value="0"
                               data-sk-auto-submit <?= $filters['premium'] === '0' ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="f-free"><?= e(__('templates.free')) ?></label>
                    </div>
                </fieldset>

                <?php if ($palette !== []): ?>
                    <div class="mb-3">
                        <span class="form-label small fw-semibold d-block">Colour</span>
                        <div class="sk-swatches">
                            <?php foreach ($palette as $colour): ?>
                                <?php
                                $swatchQuery = array_filter([
                                    'q' => $filters['q'],
                                    'category' => $filters['category_slug'],
                                    'language' => $filters['language'],
                                    'sort' => $filters['sort'],
                                    'color' => $colour,
                                ], static fn ($v) => (string) $v !== '');
                                ?>
                                <a class="sk-swatch <?= $filters['color'] === $colour ? 'is-active' : '' ?>"
                                   style="background: <?= eattr($colour) ?>"
                                   href="<?= e(url('templates', $swatchQuery)) ?>"
                                   title="<?= eattr($colour) ?>" aria-label="Colour <?= eattr($colour) ?>"></a>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($filters['color'] !== ''): ?>
                            <input type="hidden" name="color" value="<?= e((string) $filters['color']) ?>">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($filters['tag'] !== ''): ?>
                    <input type="hidden" name="tag" value="<?= e((string) $filters['tag']) ?>">
                <?php endif; ?>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-sm" type="submit"><?= e(__('common.search')) ?></button>
                    <?php if ($hasFilters): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('templates')) ?>"><?= e(__('templates.clear')) ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </aside>

        <div class="col-12 col-lg-9">
            <?php if ($templates === []): ?>
                <?php $view->include('partials.empty-state', [
                    'icon'        => 'search',
                    'title'       => __('templates.none_found'),
                    'text'        => __('templates.subtitle'),
                    'actionUrl'   => url('templates'),
                    'actionLabel' => __('templates.clear'),
                ]); ?>
            <?php else: ?>
                <div class="row g-3 g-md-4">
                    <?php foreach ($templates as $template): ?>
                        <div class="col-6 col-md-4">
                            <?php $view->include('partials.template-card', ['template' => $template]); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $view->start('scripts'); ?>
<script<?= App\Core\Csp::attribute() ?>>
    // Suggestions for the search box. A datalist is used on purpose: the
    // browser draws and filters the list, so there is no custom dropdown to
    // get wrong with a keyboard or a screen reader, and the box still works
    // as a plain text field if this request fails.
    window.addEventListener('load', function () {
        const input = document.getElementById('f-q');
        const list = document.getElementById('sk-q-suggestions');
        if (!input || !list) { return; }

        let timer = null;
        let last = '';
        input.addEventListener('input', function () {
            const term = input.value.trim();
            if (term.length < 2 || term === last) { return; }
            window.clearTimeout(timer);
            timer = window.setTimeout(async function () {
                last = term;
                try {
                    const response = await fetch(
                        <?= ejs(url('templates/suggest')) ?> + '?q=' + encodeURIComponent(term),
                        { headers: { 'Accept': 'application/json' } }
                    );
                    if (!response.ok) { return; }
                    const data = await response.json();
                    list.replaceChildren();
                    (data.suggestions || []).forEach(function (name) {
                        const option = document.createElement('option');
                        option.value = name;
                        list.appendChild(option);
                    });
                } catch (error) {
                    // A failed suggestion is not worth telling anyone about.
                }
            }, 220);
        });
    });
</script>
<?php $view->stop(); ?>
