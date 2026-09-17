<?php
/**
 * @var array<string,int> $stats
 * @var array<int,array<string,mixed>> $recent
 * @var array{labels:array<int,string>,views:array<int,int>,unique_views:array<int,int>,shares:array<int,int>,downloads:array<int,int>} $series
 * @var array<int,array<string,mixed>> $top
 * @var int $unreadRsvp
 * @var array<int,array<string,mixed>> $notifications
 * @var array<int,array<string,mixed>> $featured
 */
$view->extend('layouts.app');
$user = App\Core\Auth::user() ?? [];
?>

<div class="container py-4">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1"><?= e(__('dashboard.greeting', ['name' => (string) ($user['name'] ?? '')])) ?></h1>
            <p class="text-muted small mb-0"><?= e(__('dashboard.subtitle')) ?></p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('create')) ?>">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('nav.create')) ?>
        </a>
    </div>

    <?php if ($unreadRsvp > 0): ?>
        <div class="alert alert-primary d-flex align-items-center gap-2">
            <i class="bi bi-clipboard-check" aria-hidden="true"></i>
            <div class="flex-grow-1 small">
                <?= e(__('dashboard.unread_rsvp', ['count' => number_format($unreadRsvp)])) ?>
            </div>
            <a class="btn btn-sm btn-primary" href="<?= e(url('invitations')) ?>"><?= e(__('common.view')) ?></a>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <?php $view->include('partials.stat-card', [
                'icon' => 'envelope-paper', 'label' => __('dashboard.total'),
                'value' => number_format($stats['total']),
                'hint' => __('dashboard.published_count', ['count' => number_format($stats['active'])]),
                'href' => url('invitations'),
            ]); ?>
        </div>
        <div class="col-6 col-lg-3">
            <?php $view->include('partials.stat-card', [
                'icon' => 'eye', 'label' => __('analytics.views'),
                'value' => number_format($stats['views']),
                'hint' => __('analytics.unique') . ': ' . number_format($stats['unique_views']),
                'href' => url('analytics'),
            ]); ?>
        </div>
        <div class="col-6 col-lg-3">
            <?php $view->include('partials.stat-card', [
                'icon' => 'share', 'label' => __('analytics.shares'),
                'value' => number_format($stats['shares']),
                'hint' => __('analytics.downloads') . ': ' . number_format($stats['downloads']),
            ]); ?>
        </div>
        <div class="col-6 col-lg-3">
            <?php $view->include('partials.stat-card', [
                'icon' => 'clipboard-check', 'label' => __('rsvp.title'),
                'value' => number_format($stats['rsvps']),
                'hint' => __('dashboard.drafts', ['count' => number_format($stats['drafts'])]),
            ]); ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <section class="sk-panel mb-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="h6 mb-0"><?= e(__('analytics.last_30')) ?></h2>
                    <a class="btn btn-sm btn-link" href="<?= e(url('analytics')) ?>"><?= e(__('common.view_all')) ?></a>
                </div>
                <div class="sk-chart">
                    <canvas height="220" data-sk-chart='<?= eattr(json_encode([
                        'type'   => 'line',
                        'labels' => $series['labels'],
                        'datasets' => [
                            ['label' => __('analytics.views'), 'data' => $series['views']],
                            ['label' => __('analytics.unique'), 'data' => $series['unique_views']],
                            ['label' => __('analytics.shares'), 'data' => $series['shares']],
                        ],
                    ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
                </div>
            </section>

            <section class="sk-panel">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h2 class="h6 mb-0"><?= e(__('dashboard.recent')) ?></h2>
                    <a class="btn btn-sm btn-link" href="<?= e(url('invitations')) ?>"><?= e(__('common.view_all')) ?></a>
                </div>
                <?php if ($recent === []): ?>
                    <?php $view->include('partials.empty-state', [
                        'icon' => 'envelope-plus',
                        'title' => __('dashboard.empty_title'),
                        'text' => __('dashboard.empty_body'),
                        'actionUrl' => url('create'),
                        'actionLabel' => __('nav.create'),
                    ]); ?>
                <?php else: ?>
                    <div class="sk-rows">
                        <?php foreach ($recent as $invitation): ?>
                            <?php $view->include('partials.invitation-row', ['invitation' => $invitation]); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <div class="col-12 col-lg-4">
            <?php if ($top !== []): ?>
                <section class="sk-panel mb-4">
                    <h2 class="h6 mb-3"><?= e(__('analytics.top_invitations')) ?></h2>
                    <ol class="list-unstyled mb-0 d-grid gap-2 small">
                        <?php foreach ($top as $item): ?>
                            <li class="d-flex justify-content-between gap-2">
                                <a class="text-truncate" href="<?= e(url('invitations/' . $item['id'] . '/analytics')) ?>">
                                    <?= e($item['title']) ?>
                                </a>
                                <span class="text-muted flex-shrink-0"><?= number_format((int) $item['view_count']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>

            <section class="sk-panel mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h2 class="h6 mb-0"><?= e(__('dashboard.notifications')) ?></h2>
                    <a class="btn btn-sm btn-link" href="<?= e(url('notifications')) ?>"><?= e(__('common.view_all')) ?></a>
                </div>
                <?php if ($notifications === []): ?>
                    <p class="text-muted small mb-0"><?= e(__('dashboard.no_notifications')) ?></p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0 d-grid gap-2 small">
                        <?php foreach ($notifications as $note): ?>
                            <li class="d-flex gap-2">
                                <i class="bi bi-<?= e((string) ($note['icon'] ?: 'bell')) ?> text-primary" aria-hidden="true"></i>
                                <div>
                                    <div class="<?= $note['read_at'] === null ? 'fw-semibold' : '' ?>"><?= e($note['title']) ?></div>
                                    <div class="text-muted"><?= e(mb_strimwidth((string) $note['body'], 0, 80, '…')) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <?php if ($featured !== []): ?>
                <section class="sk-panel">
                    <h2 class="h6 mb-3"><?= e(__('home.featured_title')) ?></h2>
                    <div class="row g-2">
                        <?php foreach ($featured as $template): ?>
                            <div class="col-6">
                                <?php $view->include('partials.template-card', ['template' => $template]); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $view->start('scripts'); ?>
<script src="<?= e(asset('vendor/chart.umd.min.js')) ?>" defer></script>
<script src="<?= e(asset('js/charts.js')) ?>" defer></script>
<?php $view->stop(); ?>
