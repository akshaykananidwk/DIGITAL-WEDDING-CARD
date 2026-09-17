<?php
/**
 * @var array<string,int> $userStats
 * @var array<string,int> $invitationStats
 * @var array<string,int> $templateStats
 * @var array<string,int> $signups
 * @var array{series:array<string,mixed>,top:array<int,array<string,mixed>>} $platform
 * @var array<int,array<string,mixed>> $recentAudit
 * @var array<string,mixed>|null $health
 * @var array<string,mixed> $backup
 * @var array<string,mixed>|null $update
 * @var bool $updateLocked
 * @var bool $aiConfigured
 * @var array<int,array<string,mixed>> $popularTemplates
 */
$view->extend('layouts.admin');
$series = $platform['series'];
$lastBackup = $backup['latest'] ?? null;
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><?php $view->include('partials.stat-card', [
        'icon' => 'people', 'label' => 'Users', 'value' => number_format($userStats['total']),
        'hint' => '+' . number_format($userStats['new_7d']) . ' in 7 days', 'href' => url('admin/users')]); ?></div>
    <div class="col-6 col-xl-3"><?php $view->include('partials.stat-card', [
        'icon' => 'envelope-paper', 'label' => 'Invitations', 'value' => number_format($invitationStats['total']),
        'hint' => number_format($invitationStats['published']) . ' published', 'href' => url('admin/invitations')]); ?></div>
    <div class="col-6 col-xl-3"><?php $view->include('partials.stat-card', [
        'icon' => 'grid-3x3-gap', 'label' => 'Templates', 'value' => number_format($templateStats['total']),
        'hint' => number_format($templateStats['active']) . ' active', 'href' => url('admin/templates')]); ?></div>
    <div class="col-6 col-xl-3"><?php $view->include('partials.stat-card', [
        'icon' => 'eye', 'label' => 'Invitation views', 'value' => number_format($invitationStats['views']),
        'hint' => 'All time', 'href' => url('admin/analytics')]); ?></div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <section class="sk-panel mb-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h6 mb-0">Platform activity · 30 days</h2>
                <a class="btn btn-sm btn-link" href="<?= e(url('admin/analytics')) ?>">Analytics</a>
            </div>
            <div class="sk-chart">
                <canvas height="240" data-sk-chart='<?= eattr(json_encode([
                    'type' => 'line',
                    'labels' => $series['labels'],
                    'datasets' => [
                        ['label' => 'Views', 'data' => $series['views']],
                        ['label' => 'Shares', 'data' => $series['shares']],
                        ['label' => 'RSVP', 'data' => $series['rsvps']],
                    ],
                ])) ?>'></canvas>
            </div>
        </section>

        <section class="sk-panel mb-4">
            <h2 class="h6 mb-2">New accounts · 30 days</h2>
            <div class="sk-chart sk-chart--sm">
                <canvas height="200" data-sk-chart='<?= eattr(json_encode([
                    'type' => 'bar',
                    'legend' => false,
                    'labels' => array_keys($signups),
                    'datasets' => [['label' => 'Signups', 'data' => array_map('intval', array_values($signups))]],
                ])) ?>'></canvas>
            </div>
        </section>

        <section class="sk-panel">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h6 mb-0">Recent admin activity</h2>
                <a class="btn btn-sm btn-link" href="<?= e(url('admin/audit')) ?>">Audit log</a>
            </div>
            <div class="sk-table-wrap">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>When</th><th>Action</th><th>By</th><th class="d-none d-md-table-cell">Detail</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentAudit as $entry): ?>
                        <tr>
                            <td class="text-nowrap small text-muted"><?= e(date('d M H:i', strtotime((string) $entry['created_at']))) ?></td>
                            <td><code class="sk-code"><?= e((string) $entry['action']) ?></code></td>
                            <td class="small"><?= e((string) ($entry['actor_name'] ?? 'system')) ?></td>
                            <td class="d-none d-md-table-cell small text-muted">
                                <?= e(mb_strimwidth((string) ($entry['description'] ?? ''), 0, 70, '…')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentAudit === []): ?>
                        <tr><td colspan="4" class="text-muted small">Nothing recorded yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-4">
        <section class="sk-panel mb-4">
            <h2 class="h6 mb-3">System</h2>
            <dl class="row small mb-3">
                <dt class="col-6 text-muted">Health</dt>
                <dd class="col-6">
                    <?php if ($health === null): ?>
                        <span class="text-muted">Not run yet</span>
                    <?php else: ?>
                        <?php $view->include('admin.partials.health-dot', ['status' => (string) $health['status']]); ?>
                    <?php endif; ?>
                </dd>
                <dt class="col-6 text-muted">Version</dt>
                <dd class="col-6">v<?= e($appVersion) ?></dd>
                <dt class="col-6 text-muted">Backups</dt>
                <dd class="col-6">
                    <?php if (!is_array($lastBackup)): ?>
                        <span class="text-muted">None yet</span>
                    <?php else: ?>
                        <?= e(date('d M Y H:i', strtotime((string) $lastBackup['created_at']))) ?>
                        <span class="d-block text-muted"><?= number_format((int) $backup['count']) ?> stored</span>
                    <?php endif; ?>
                </dd>
                <dt class="col-6 text-muted">Last update</dt>
                <dd class="col-6">
                    <?php if ($update === null): ?>
                        <span class="text-muted">None</span>
                    <?php else: ?>
                        <?= e((string) $update['status']) ?>
                        <span class="text-muted d-block"><?= e(date('d M Y', strtotime((string) $update['created_at']))) ?></span>
                    <?php endif; ?>
                </dd>
                <dt class="col-6 text-muted">AI (Gemini)</dt>
                <dd class="col-6"><?= $aiConfigured ? 'Configured' : '<span class="text-muted">Not configured</span>' ?></dd>
            </dl>

            <?php if ($updateLocked): ?>
                <div class="alert alert-warning small py-2">
                    An update is in progress, or a stale lock is present.
                    <a href="<?= e(url('admin/system/update')) ?>">Review</a>
                </div>
            <?php endif; ?>

            <div class="d-grid gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/system/health')) ?>">Run health check</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/system/update')) ?>">Check for updates</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/system/backups')) ?>">Backups</a>
            </div>
        </section>

        <?php if ($platform['top'] !== []): ?>
            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3">Most viewed invitations</h2>
                <ol class="list-unstyled mb-0 d-grid gap-2 small">
                    <?php foreach ($platform['top'] as $item): ?>
                        <li class="d-flex justify-content-between gap-2">
                            <a class="text-truncate" href="<?= e(url('admin/invitations/' . $item['id'])) ?>"><?= e($item['title']) ?></a>
                            <span class="text-muted flex-shrink-0"><?= number_format((int) $item['view_count']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>

        <?php if ($popularTemplates !== []): ?>
            <section class="sk-panel">
                <h2 class="h6 mb-3">Most used templates</h2>
                <ol class="list-unstyled mb-0 d-grid gap-2 small">
                    <?php foreach ($popularTemplates as $item): ?>
                        <li class="d-flex justify-content-between gap-2">
                            <a class="text-truncate" href="<?= e(url('admin/templates/' . $item['id'] . '/edit')) ?>"><?= e($item['name']) ?></a>
                            <span class="text-muted flex-shrink-0"><?= number_format((int) $item['use_count']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php $view->start('scripts'); ?>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('vendor/chart.umd.min.js')) ?>"></script>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('js/charts.js')) ?>"></script>
<?php $view->stop(); ?>
