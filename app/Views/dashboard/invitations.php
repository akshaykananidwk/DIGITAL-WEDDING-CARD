<?php
/**
 * @var array<int,array<string,mixed>> $invitations
 * @var array<string,mixed> $pagination
 * @var array{status:string,q:string} $filters
 * @var int $total
 */
$view->extend('layouts.app');
$statuses = [
    ''            => __('common.all'),
    'published'   => __('common.published'),
    'draft'       => __('common.draft'),
    'unpublished' => __('builder.unpublish'),
];
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><?= e(__('nav.invitations')) ?></h1>
            <p class="text-muted small mb-0"><?= e(number_format($total)) ?></p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('create')) ?>">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('nav.create')) ?>
        </a>
    </div>

    <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('invitations')) ?>" role="search">
        <div class="col-12 col-sm">
            <label class="form-label small mb-1" for="q"><?= e(__('common.search')) ?></label>
            <input class="form-control form-control-sm" type="search" id="q" name="q"
                   maxlength="80" value="<?= e((string) $filters['q']) ?>">
        </div>
        <div class="col-6 col-sm-auto">
            <label class="form-label small mb-1" for="status"><?= e(__('common.status')) ?></label>
            <select class="form-select form-select-sm" id="status" name="status" data-sk-auto-submit>
                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= e((string) $value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-auto">
            <button class="btn btn-sm btn-outline-secondary w-100" type="submit"><?= e(__('common.search')) ?></button>
        </div>
    </form>

    <?php if ($invitations === []): ?>
        <?php $view->include('partials.empty-state', [
            'icon'        => 'envelope-plus',
            'title'       => __('dashboard.empty_title'),
            'text'        => __('dashboard.empty_body'),
            'actionUrl'   => url('create'),
            'actionLabel' => __('nav.create'),
        ]); ?>
    <?php else: ?>
        <div class="sk-panel">
            <div class="sk-rows">
                <?php foreach ($invitations as $invitation): ?>
                    <?php $view->include('partials.invitation-row', ['invitation' => $invitation]); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
    <?php endif; ?>
</div>
