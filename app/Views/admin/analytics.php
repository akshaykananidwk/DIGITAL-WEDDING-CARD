<?php
/**
 * @var int $days
 * @var array{series:array<string,mixed>,top:array<int,array<string,mixed>>} $platform
 * @var array<string,int> $signups
 * @var array<int,array<string,mixed>> $templates
 */
$view->extend('layouts.admin');
$series = $platform['series'];
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <p class="text-muted small mb-0">
        Aggregate counts only. No visitor is identified, no IP address is stored and no third-party analytics
        script is loaded.
    </p>
    <div class="btn-group btn-group-sm" role="group">
        <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $value => $label): ?>
            <a class="btn btn-outline-secondary <?= $days === $value ? 'active' : '' ?>"
               href="<?= e(url('admin/analytics', ['days' => $value])) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <section class="sk-panel mb-4">
            <h2 class="h6 mb-3">Invitation activity</h2>
            <div class="sk-chart">
                <canvas height="240" data-sk-chart='<?= eattr(json_encode([
                    'type' => 'line',
                    'labels' => $series['labels'],
                    'datasets' => [
                        ['label' => 'Views', 'data' => $series['views']],
                        ['label' => 'Unique', 'data' => $series['unique']],
                        ['label' => 'Shares', 'data' => $series['shares']],
                        ['label' => 'Downloads', 'data' => $series['downloads']],
                        ['label' => 'RSVP', 'data' => $series['rsvps']],
                    ],
                ])) ?>'></canvas>
            </div>
        </section>

        <section class="sk-panel">
            <h2 class="h6 mb-3">New accounts</h2>
            <div class="sk-chart sk-chart--sm">
                <canvas height="200" data-sk-chart='<?= eattr(json_encode([
                    'type' => 'bar',
                    'legend' => false,
                    'labels' => array_keys($signups),
                    'datasets' => [['label' => 'Signups', 'data' => array_map('intval', array_values($signups))]],
                ])) ?>'></canvas>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-4">
        <section class="sk-panel mb-4">
            <h2 class="h6 mb-3">Most viewed invitations</h2>
            <ol class="list-unstyled mb-0 d-grid gap-2 small">
                <?php foreach ($platform['top'] as $item): ?>
                    <li class="d-flex justify-content-between gap-2">
                        <a class="text-truncate" href="<?= e(url('admin/invitations/' . $item['id'])) ?>">
                            <?= e($item['title']) ?>
                        </a>
                        <span class="text-muted flex-shrink-0"><?= number_format((int) $item['view_count']) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if ($platform['top'] === []): ?>
                    <li class="text-muted">No activity yet.</li>
                <?php endif; ?>
            </ol>
        </section>

        <section class="sk-panel">
            <h2 class="h6 mb-3">Most used templates</h2>
            <div class="sk-chart sk-chart--sm">
                <canvas height="220" data-sk-chart='<?= eattr(json_encode([
                    'type' => 'bar',
                    'legend' => false,
                    'labels' => array_map(
                        static fn (array $r): string => mb_strimwidth((string) $r['name'], 0, 18, '…'),
                        $templates
                    ),
                    'datasets' => [[
                        'label' => 'Invitations',
                        'data'  => array_map(static fn (array $r): int => (int) $r['use_count'], $templates),
                    ]],
                ])) ?>'></canvas>
            </div>
        </section>
    </div>
</div>

<?php $view->start('scripts'); ?>
<script src="<?= e(asset('vendor/chart.umd.min.js')) ?>" defer></script>
<script src="<?= e(asset('js/charts.js')) ?>" defer></script>
<?php $view->stop(); ?>
