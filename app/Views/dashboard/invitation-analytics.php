<?php
/**
 * @var array<string,mixed> $invitation
 * @var array<string,mixed> $report
 * @var string $window
 * @var bool $advanced
 */
$view->extend('layouts.app');
$windows = [
    'today' => __('analytics.today'),
    '7d'    => __('analytics.last_7'),
    '30d'   => __('analytics.last_30'),
    '90d'   => '90d',
    'all'   => __('analytics.all_time'),
];
$totals = $report['totals'];
$lifetime = $report['lifetime'];
$series = $report['series'];

$breakdown = static function (array $data): array {
    arsort($data);
    return array_slice($data, 0, 8, true);
};
?>
<div class="container py-4">
    <nav aria-label="Breadcrumb" class="small mb-2">
        <a href="<?= e(url('invitations')) ?>"><?= e(__('nav.invitations')) ?></a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(url('builder/' . $invitation['id'])) ?>"><?= e($invitation['title']) ?></a>
    </nav>

    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><?= e(__('analytics.title')) ?></h1>
            <p class="text-muted small mb-0">
                <?php if ($report['last_view'] !== null): ?>
                    <?= e(__('analytics.last_viewed')) ?>:
                    <?= e(date('d M Y, H:i', strtotime((string) $report['last_view']))) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <div class="btn-group btn-group-sm" role="group">
                <?php foreach ($windows as $value => $label): ?>
                    <a class="btn btn-outline-secondary <?= $window === $value ? 'active' : '' ?>"
                       href="<?= e(url('invitations/' . $invitation['id'] . '/analytics', ['window' => $value])) ?>">
                        <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <a class="btn btn-sm btn-outline-secondary"
               href="<?= e(url('invitations/' . $invitation['id'] . '/analytics/export', ['window' => $window])) ?>">
                <i class="bi bi-download me-1" aria-hidden="true"></i><?= e(__('analytics.export')) ?>
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['eye', __('analytics.views'), $totals['views'], $lifetime['views']],
            ['person', __('analytics.unique'), $totals['unique_views'], $lifetime['unique_views']],
            ['share', __('analytics.shares'), $totals['shares'], $lifetime['shares']],
            ['download', __('analytics.downloads'), $totals['downloads'], $lifetime['downloads']],
            ['qr-code', __('analytics.qr_scans'), $totals['qr_scans'], $lifetime['qr_scans']],
            ['clipboard-check', __('rsvp.title'), $totals['rsvps'], $lifetime['rsvps']],
        ];
        foreach ($cards as [$icon, $label, $value, $all]): ?>
            <div class="col-6 col-lg-2">
                <?php $view->include('partials.stat-card', [
                    'icon'  => $icon,
                    'label' => $label,
                    'value' => number_format((int) $value),
                    'hint'  => __('analytics.all_time') . ': ' . number_format((int) $all),
                ]); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="sk-panel mb-4">
        <h2 class="h6 mb-3"><?= e($windows[$window] ?? '') ?></h2>
        <?php if (array_sum($series['views']) === 0): ?>
            <p class="text-muted small mb-0"><?= e(__('analytics.no_data')) ?></p>
        <?php else: ?>
            <div class="sk-chart">
                <canvas height="240" data-sk-chart='<?= eattr(json_encode([
                    'type'   => 'line',
                    'labels' => $series['labels'],
                    'datasets' => [
                        ['label' => __('analytics.views'), 'data' => $series['views']],
                        ['label' => __('analytics.unique'), 'data' => $series['unique_views']],
                        ['label' => __('analytics.shares'), 'data' => $series['shares']],
                    ],
                ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($advanced): ?>
        <div class="row g-4">
            <?php
            $panels = [
                [__('analytics.devices'), $report['devices'], 'doughnut'],
                [__('analytics.browsers'), $report['browsers'], 'doughnut'],
                [__('analytics.channels'), $report['shares'], 'bar'],
                [__('analytics.referrers'), $report['referrers'], 'bar'],
            ];
            foreach ($panels as [$title, $data, $type]): ?>
                <div class="col-12 col-md-6">
                    <section class="sk-panel h-100">
                        <h2 class="h6 mb-3"><?= e($title) ?></h2>
                        <?php $data = $breakdown((array) $data); ?>
                        <?php if ($data === []): ?>
                            <p class="text-muted small mb-0"><?= e(__('analytics.no_data')) ?></p>
                        <?php else: ?>
                            <div class="sk-chart sk-chart--sm">
                                <canvas height="200" data-sk-chart='<?= eattr(json_encode([
                                    'type'   => $type,
                                    'legend' => $type !== 'bar',
                                    'labels' => array_keys($data),
                                    'datasets' => [['label' => $title, 'data' => array_values($data)]],
                                ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4 mt-1">
        <div class="col-12 col-md-6">
            <section class="sk-panel h-100">
                <h2 class="h6 mb-3"><?= e(__('rsvp.title')) ?></h2>
                <dl class="row small mb-3">
                    <dt class="col-7 text-muted"><?= e(__('rsvp.attending')) ?></dt>
                    <dd class="col-5 text-end mb-1"><?= number_format((int) $report['rsvp']['yes']) ?></dd>
                    <dt class="col-7 text-muted"><?= e(__('rsvp.maybe')) ?></dt>
                    <dd class="col-5 text-end mb-1"><?= number_format((int) $report['rsvp']['maybe']) ?></dd>
                    <dt class="col-7 text-muted"><?= e(__('rsvp.declined')) ?></dt>
                    <dd class="col-5 text-end mb-1"><?= number_format((int) $report['rsvp']['no']) ?></dd>
                    <dt class="col-7 text-muted"><?= e(__('rsvp.guests')) ?></dt>
                    <dd class="col-5 text-end mb-0"><?= number_format((int) $report['rsvp']['guests']) ?></dd>
                </dl>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('invitations/' . $invitation['id'] . '/rsvp')) ?>">
                    <?= e(__('common.view_all')) ?>
                </a>
            </section>
        </div>
        <div class="col-12 col-md-6">
            <section class="sk-panel h-100">
                <h2 class="h6 mb-2"><?= e(__('nav.privacy')) ?></h2>
                <p class="text-muted small mb-0">
                    <i class="bi bi-shield-check me-1" aria-hidden="true"></i><?= e(__('analytics.privacy_note')) ?>
                </p>
            </section>
        </div>
    </div>
</div>

<?php $view->start('scripts'); ?>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('vendor/chart.umd.min.js')) ?>"></script>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('js/charts.js')) ?>"></script>
<?php $view->stop(); ?>
