<?php
/** @var array<int,array<string,mixed>> $categories */
$view->extend('layouts.app');
?>
<div class="container py-4">
    <h1 class="h3 mb-1"><?= e(__('nav.categories')) ?></h1>
    <p class="text-muted"><?= e(__('home.occasions_subtitle')) ?></p>

    <div class="row g-4 mt-1">
        <?php foreach ($categories as $category): ?>
            <div class="col-12 col-lg-6">
                <div class="sk-panel h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="sk-occasion__icon" aria-hidden="true"><i class="bi bi-<?= e((string) ($category['icon'] ?: 'stars')) ?>"></i></span>
                        <h2 class="h5 mb-0">
                            <a href="<?= e(url('category/' . $category['slug'])) ?>"><?= e($category['name']) ?></a>
                        </h2>
                    </div>
                    <?php if (($desc = (string) $category['description']) !== ''): ?>
                        <p class="small text-muted"><?= e($desc) ?></p>
                    <?php endif; ?>
                    <ul class="list-unstyled row row-cols-1 row-cols-sm-2 g-1 small mb-0">
                        <?php foreach ($category['subcategories'] as $sub): ?>
                            <li class="col">
                                <a href="<?= e(url('category/' . $category['slug'] . '/' . $sub['slug'])) ?>">
                                    <i class="bi bi-chevron-right small" aria-hidden="true"></i><?= e($sub['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
