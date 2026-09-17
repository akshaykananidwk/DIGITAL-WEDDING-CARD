<?php
/**
 * @var array<string,mixed> $category
 * @var array<string,mixed>|null $subcategory
 * @var array<int,array<string,mixed>> $subcategories
 * @var array<int,array<string,mixed>> $templates
 * @var array<string,mixed> $pagination
 * @var int $total
 */
$view->extend('layouts.app');
$active = $subcategory ?? $category;
?>
<div class="container py-4">
    <nav aria-label="Breadcrumb" class="small mb-2">
        <a href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(url('categories')) ?>"><?= e(__('nav.categories')) ?></a>
        <?php if ($subcategory !== null): ?>
            <span aria-hidden="true">/</span>
            <a href="<?= e(url('category/' . $category['slug'])) ?>"><?= e($category['name']) ?></a>
        <?php endif; ?>
    </nav>

    <h1 class="h3 mb-1"><?= e($active['name']) ?></h1>
    <p class="text-muted small">
        <?= e(__('templates.results', ['count' => number_format($total)])) ?>
        <?php if (($desc = (string) ($active['description'] ?? '')) !== ''): ?>
            <span aria-hidden="true">·</span> <?= e($desc) ?>
        <?php endif; ?>
    </p>

    <?php if ($subcategories !== []): ?>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a class="sk-chip <?= $subcategory === null ? 'is-active' : '' ?>" href="<?= e(url('category/' . $category['slug'])) ?>">
                <?= e(__('common.all')) ?>
            </a>
            <?php foreach ($subcategories as $sub): ?>
                <a class="sk-chip <?= $subcategory !== null && (int) $sub['id'] === (int) $subcategory['id'] ? 'is-active' : '' ?>"
                   href="<?= e(url('category/' . $category['slug'] . '/' . $sub['slug'])) ?>">
                    <?= e($sub['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($templates === []): ?>
        <?php $view->include('partials.empty-state', [
            'icon'        => 'inbox',
            'title'       => __('templates.none_found'),
            'actionUrl'   => url('templates'),
            'actionLabel' => __('templates.title'),
        ]); ?>
    <?php else: ?>
        <div class="row g-3 g-md-4">
            <?php foreach ($templates as $template): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <?php $view->include('partials.template-card', ['template' => $template]); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
    <?php endif; ?>
</div>
