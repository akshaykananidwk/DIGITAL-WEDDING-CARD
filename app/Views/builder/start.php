<?php
/**
 * Step 1: what is the occasion?
 *
 * @var array<int,array<string,mixed>> $categories
 * @var bool $aiEnabled
 */
$view->extend('layouts.app');
?>
<div class="container py-4">
    <?php $view->include('partials.wizard-steps', ['step' => 1]); ?>

    <h1 class="h4 mb-1 mt-4"><?= e(__('builder.step_category')) ?></h1>
    <p class="text-muted small"><?= e(__('builder.category_hint')) ?></p>

    <div class="row g-4 mt-1">
        <?php foreach ($categories as $category): ?>
            <div class="col-12 col-lg-6">
                <section class="sk-panel h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="sk-occasion__icon" aria-hidden="true">
                            <i class="bi bi-<?= e((string) ($category['icon'] ?: 'stars')) ?>"></i>
                        </span>
                        <div>
                            <h2 class="h6 mb-0"><?= e($category['name']) ?></h2>
                            <p class="small text-muted mb-0"><?= e((string) $category['description']) ?></p>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($category['subcategories'] as $sub): ?>
                            <a class="sk-chip"
                               href="<?= e(url('create/templates', [
                                   'category' => (string) $category['slug'],
                                   'subcategory' => (string) $sub['slug'],
                               ])) ?>">
                                <?= e($sub['name']) ?>
                            </a>
                        <?php endforeach; ?>
                        <a class="sk-chip sk-chip--ghost"
                           href="<?= e(url('create/templates', ['category' => (string) $category['slug']])) ?>">
                            <?= e(__('common.all')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </section>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($aiEnabled): ?>
        <section class="sk-panel mt-4">
            <h2 class="h6 mb-1"><?= e(__('builder.ai_pick_title')) ?></h2>
            <p class="small text-muted"><?= e(__('builder.ai_pick_hint')) ?></p>
            <form class="row g-2 align-items-end" method="post" action="<?= e(url('create/suggest')) ?>">
                <?= csrf_field() ?>
                <div class="col-12 col-md-4">
                    <label class="form-label small mb-1" for="event_type"><?= e(__('builder.ai_event')) ?></label>
                    <input class="form-control form-control-sm" type="text" id="event_type" name="event_type"
                           maxlength="80" placeholder="<?= eattr(__('builder.ai_event_placeholder')) ?>" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small mb-1" for="theme"><?= e(__('builder.ai_theme')) ?></label>
                    <input class="form-control form-control-sm" type="text" id="theme" name="theme"
                           maxlength="80" placeholder="<?= eattr(__('builder.ai_theme_placeholder')) ?>">
                </div>
                <div class="col-8 col-md-2">
                    <label class="form-label small mb-1" for="ai_language"><?= e(__('common.language')) ?></label>
                    <select class="form-select form-select-sm" id="ai_language" name="language">
                        <option value="gu">ગુજરાતી</option>
                        <option value="hi">हिन्दी</option>
                        <option value="en">English</option>
                    </select>
                </div>
                <div class="col-4 col-md-2">
                    <button class="btn btn-sm btn-primary w-100" type="submit"
                            data-sk-loading="<?= eattr(__('common.loading')) ?>">
                        <?= e(__('templates.ai_suggested')) ?>
                    </button>
                </div>
            </form>
        </section>
    <?php endif; ?>
</div>
