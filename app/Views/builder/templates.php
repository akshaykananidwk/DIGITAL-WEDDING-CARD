<?php
/**
 * Step 2: pick a template.
 *
 * @var array<int,array<string,mixed>> $templates
 * @var array<string,mixed>|null $pagination
 * @var array<string,mixed>|null $category
 * @var array<string,mixed>|null $subcategory
 * @var array<int,array<string,mixed>> $subcategories
 * @var array<int,array<string,mixed>> $categories
 * @var array<string,mixed> $filters
 * @var int $total
 * @var array<string,mixed>|null $suggestion
 * @var bool $aiEnabled
 */
$view->extend('layouts.app');
$suggestion = $suggestion ?? null;
?>
<div class="container py-4">
    <?php $view->include('partials.wizard-steps', ['step' => 2]); ?>

    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mt-4 mb-3">
        <div>
            <h1 class="h4 mb-1">
                <?= $suggestion !== null ? e(__('templates.ai_suggested')) : e(__('builder.step_template')) ?>
            </h1>
            <p class="text-muted small mb-0">
                <?= e(__('templates.results', ['count' => number_format($total)])) ?>
                <?php if ($category !== null): ?>
                    <span aria-hidden="true">·</span> <?= e($category['name']) ?>
                <?php endif; ?>
                <?php if ($subcategory !== null): ?>
                    <span aria-hidden="true">·</span> <?= e($subcategory['name']) ?>
                <?php endif; ?>
            </p>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('create')) ?>">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= e(__('builder.back')) ?>
        </a>
    </div>

    <?php if ($suggestion !== null): ?>
        <div class="alert alert-<?= $suggestion['source'] === 'ai' ? 'primary' : 'secondary' ?> small">
            <i class="bi bi-<?= $suggestion['source'] === 'ai' ? 'stars' : 'sliders' ?> me-1" aria-hidden="true"></i>
            <?= e((string) $suggestion['reason']) ?>
        </div>
    <?php endif; ?>

    <?php if ($suggestion === null): ?>
        <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('create/templates')) ?>">
            <input type="hidden" name="category" value="<?= e((string) ($category['slug'] ?? '')) ?>">
            <div class="col-12 col-sm">
                <label class="form-label small mb-1" for="q"><?= e(__('templates.search')) ?></label>
                <input class="form-control form-control-sm" type="search" id="q" name="q"
                       maxlength="80" value="<?= e((string) ($filters['q'] ?? '')) ?>">
            </div>
            <?php if ($subcategories !== []): ?>
                <div class="col-6 col-sm-auto">
                    <label class="form-label small mb-1" for="subcategory"><?= e(__('nav.categories')) ?></label>
                    <select class="form-select form-select-sm" id="subcategory" name="subcategory" data-sk-auto-submit>
                        <option value=""><?= e(__('common.all')) ?></option>
                        <?php foreach ($subcategories as $sub): ?>
                            <option value="<?= e($sub['slug']) ?>"
                                <?= $subcategory !== null && (int) $sub['id'] === (int) $subcategory['id'] ? 'selected' : '' ?>>
                                <?= e($sub['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-6 col-sm-auto">
                <label class="form-label small mb-1" for="language"><?= e(__('common.language')) ?></label>
                <select class="form-select form-select-sm" id="language" name="language" data-sk-auto-submit>
                    <option value=""><?= e(__('templates.all_languages')) ?></option>
                    <option value="gu" <?= ($filters['language'] ?? '') === 'gu' ? 'selected' : '' ?>>ગુજરાતી</option>
                    <option value="hi" <?= ($filters['language'] ?? '') === 'hi' ? 'selected' : '' ?>>हिन्दी</option>
                    <option value="en" <?= ($filters['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                </select>
            </div>
            <div class="col-12 col-sm-auto">
                <button class="btn btn-sm btn-outline-secondary w-100" type="submit"><?= e(__('common.search')) ?></button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($templates === []): ?>
        <?php $view->include('partials.empty-state', [
            'icon'        => 'palette',
            'title'       => __('templates.none_found'),
            'actionUrl'   => url('create/templates'),
            'actionLabel' => __('templates.clear'),
        ]); ?>
    <?php else: ?>
        <div class="row g-3 g-md-4">
            <?php foreach ($templates as $template): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <?php $view->include('partials.template-card', [
                        'template'   => $template,
                        'selectable' => true,
                    ]); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (is_array($pagination)): ?>
            <?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
