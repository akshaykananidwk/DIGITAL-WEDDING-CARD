<?php
/** @var array<int,array<string,mixed>> $notifications */
$view->extend('layouts.app');
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0"><?= e(__('dashboard.notifications')) ?></h1>
        <?php if ($notifications !== []): ?>
            <form method="post" action="<?= e(url('notifications/read-all')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= e(__('rsvp.mark_read')) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($notifications === []): ?>
        <?php $view->include('partials.empty-state', ['icon' => 'bell', 'title' => __('dashboard.no_notifications')]); ?>
    <?php else: ?>
        <div class="sk-panel">
            <div class="sk-rows">
                <?php foreach ($notifications as $note): ?>
                    <div class="sk-row">
                        <div class="sk-row__main d-flex gap-3">
                            <i class="bi bi-<?= e((string) ($note['icon'] ?: 'bell')) ?> fs-5 text-primary" aria-hidden="true"></i>
                            <div>
                                <div class="<?= $note['read_at'] === null ? 'fw-semibold' : '' ?>"><?= e($note['title']) ?></div>
                                <div class="small text-muted"><?= e((string) $note['body']) ?></div>
                                <div class="sk-row__meta">
                                    <span><?= e(date('d M Y, H:i', strtotime((string) $note['created_at']))) ?></span>
                                    <?php if (($link = (string) ($note['url'] ?? '')) !== ''): ?>
                                        <a href="<?= e(url($link)) ?>"><?= e(__('common.view')) ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($note['read_at'] === null): ?>
                            <div class="sk-row__actions">
                                <form method="post" action="<?= e(url('notifications/' . $note['id'] . '/read')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-secondary" type="submit"
                                            aria-label="<?= eattr(__('rsvp.mark_read')) ?>">
                                        <i class="bi bi-check2" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
