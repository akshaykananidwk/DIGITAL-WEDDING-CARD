<?php
/**
 * @var array{stats:array<string,int>,series:array<string,mixed>,top:array<int,array<string,mixed>>} $overview
 * @var string $window
 * @var array<int,array<string,mixed>> $invitations
 * @var bool $advanced
 */
$view->extend('layouts.app');
$windows = [
    'today' => __('analytics.today'),
    '7d'    => __('analytics.last_7'),
    '30d'   => __('analytics.last_30'),
    'all'   => __('analytics.all_time'),
];
$stats = $overview['stats'];
$series = $overview['series'];
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0"><?= e(__('analytics.title')) ?></h1>
        <div class="btn-group btn-group-sm" role="group" aria-label="<?= eattr(__('analytics.title')) ?>">
            <?php foreach ($windows as $value => $label): ?>
                <a class="btn btn-outline-secondary <?= $window === $value ? 'active' : '' ?>"
                   href="<?= e(url('analytics', ['window' => $value])) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
            'icon' => 'eye', 'label' => __('analytics.views'), 'value' => number_format($stats['views'])]); ?></div>
        <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
            'icon' => 'person', 'label' => __('analytics.unique'), 'value' => number_format($stats['unique_views'])]); ?></div>
        <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
            'icon' => 'share', 'label' => __('analytics.shares'), 'value' => number_format($stats['shares'])]); ?></div>
        <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
            'icon' => 'download', 'label' => __('analytics.downloads'), 'value' => number_format($stats['downloads'])]); ?></div>
    </div>

    <section class="sk-panel mb-4">
        <h2 class="h6 mb-3"><?= e($windows[$window] ?? __('analytics.last_30')) ?></h2>
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
                        ['label' => __('analytics.downloads'), 'data' => $series['downloads']],
                    ],
                ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
            </div>
        <?php endif; ?>
    </section>

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <section class="sk-panel p-0">
                <div class="p-3 pb-0"><h2 class="h6 mb-0"><?= e(__('nav.invitations')) ?></h2></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col"><?= e(__('nav.invitations')) ?></th>
                            <th scope="col" class="text-end"><?= e(__('analytics.views')) ?></th>
                            <th scope="col" class="text-end d-none d-sm-table-cell"><?= e(__('analytics.shares')) ?></th>
                            <th scope="col" class="text-end"><?= e(__('rsvp.title')) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($invitations as $invitation): ?>
                            <tr>
                                <td class="text-truncate" style="max-width:16rem">
                                    <a href="<?= e(url('invitations/' . $invitation['id'] . '/analytics')) ?>">
                                        <?= e($invitation['title']) ?>
                                    </a>
                                </td>
                                <td class="text-end"><?= number_format((int) $invitation['view_count']) ?></td>
                                <td class="text-end d-none d-sm-table-cell"><?= number_format((int) $invitation['share_count']) ?></td>
                                <td class="text-end"><?= number_format((int) $invitation['rsvp_count']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($invitations === []): ?>
                            <tr><td colspan="4" class="text-muted small"><?= e(__('analytics.no_data')) ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-5">
            <?php if ($overview['top'] !== []): ?>
                <section class="sk-panel mb-4">
                    <h2 class="h6 mb-3"><?= e(__('analytics.top_invitations')) ?></h2>
                    <div class="sk-chart">
                        <canvas height="220" data-sk-chart='<?= eattr(json_encode([
                            'type'   => 'bar',
                            'legend' => false,
                            'labels' => array_map(
                                static fn (array $r): string => mb_strimwidth((string) $r['title'], 0, 18, '…'),
                                $overview['top']
                            ),
                            'datasets' => [[
                                'label' => __('analytics.views'),
                                'data'  => array_map(static fn (array $r): int => (int) $r['view_count'], $overview['top']),
                            ]],
                        ], JSON_UNESCAPED_UNICODE)) ?>'></canvas>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!$advanced): ?>
                <div class="alert alert-secondary small mb-4">
                    <?= e(__('analytics.basic_only')) ?>
                </div>
            <?php endif; ?>

            <p class="text-muted small mb-0">
                <i class="bi bi-shield-check me-1" aria-hidden="true"></i><?= e(__('analytics.privacy_note')) ?>
            </p>
        </div>
    </div>
</div>

<?php $view->start('scripts'); ?>
<script src="<?= e(asset('vendor/chart.umd.min.js')) ?>" defer></script>
<script src="<?= e(asset('js/charts.js')) ?>" defer></script>
<?php $view->stop(); ?>
