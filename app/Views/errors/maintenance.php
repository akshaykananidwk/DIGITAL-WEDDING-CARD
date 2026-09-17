<?php
/**
 * Maintenance page, shown while an update or restore is running.
 *
 * @var string $message
 * @var string|null $started_at
 */
$view->extend('layouts.plain', ['title' => __('errors.maintenance')]);
?>
<div class="d-flex align-items-center justify-content-center min-vh-100 p-4">
    <div class="text-center" style="max-width:34rem">
        <div class="sk-brand justify-content-center mb-3">
            <span class="sk-brand__mark" aria-hidden="true">શ</span>
            <span><?= e((string) (setting('site_name') ?: config('app.name'))) ?></span>
        </div>

        <div class="sk-panel">
            <div class="spinner-border text-danger mb-3" role="status" aria-hidden="true"></div>
            <h1 class="h4 mb-2"><?= e(__('errors.maintenance')) ?></h1>
            <p class="text-muted mb-0"><?= e($message) ?></p>
            <?php if (!empty($started_at)): ?>
                <p class="small text-muted mt-3 mb-0">
                    Started <?= e(date('H:i', strtotime($started_at))) ?> IST
                </p>
            <?php endif; ?>
        </div>

        <p class="small text-muted mt-3 mb-0">
            This page refreshes automatically.
        </p>
    </div>
</div>

<?php $view->start('head'); ?>
<meta http-equiv="refresh" content="20">
<?php $view->stop(); ?>
