<?php
/**
 * @var array<string,mixed> $meta
 * @var array<string,mixed>|null $latestHealth
 * @var array<string,mixed> $backup
 * @var array<string,mixed> $update
 * @var bool $maintenance
 * @var array<string,array<string,mixed>> $cron
 * @var array<int,array<string,mixed>> $logs
 * @var string $version
 */
$view->extend('layouts.admin');
$mb = static fn (int $bytes): string => $bytes <= 0 ? '—' : number_format($bytes / 1048576, 1) . ' MB';
$gb = static fn (int $bytes): string => $bytes <= 0 ? '—' : number_format($bytes / 1073741824, 1) . ' GB';
?>
<div class="row g-4">
    <div class="col-12 col-xl-7">
        <section class="sk-panel mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h6 mb-0">Environment</h2>
                <span class="badge text-bg-light text-dark">v<?= e($version) ?></span>
            </div>
            <div class="sk-table-wrap">
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php foreach ([
                        'PHP'              => $meta['php_version'] . ' (' . $meta['php_sapi'] . ')',
                        'Database'         => $meta['db_version'] . ' · ' . $mb((int) $meta['db_size']),
                        'Server'           => $meta['server'] . ' · ' . $meta['os'],
                        'Timezone'         => $meta['timezone'] . ' · ' . $meta['server_time'],
                        'Memory limit'     => $meta['memory_limit'],
                        'Upload limit'     => $meta['max_upload'] . ' (POST ' . $meta['post_max'] . ')',
                        'Max execution'    => $meta['max_execution'] . 's',
                        'Disk free'        => $gb((int) $meta['disk_free']) . ' of ' . $gb((int) $meta['disk_total']),
                        'Installed version' => $meta['installed_version'],
                    ] as $label => $value): ?>
                        <tr>
                            <th class="text-muted fw-normal" scope="row" style="width:40%"><?= e($label) ?></th>
                            <td><?= e((string) $value) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="sk-panel mb-4">
            <h2 class="h6 mb-3">Scheduled tasks</h2>
            <div class="sk-table-wrap">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Task</th><th>Last run</th><th>Result</th></tr></thead>
                    <tbody>
                    <?php foreach ($cron as $name => $task): ?>
                        <?php $last = $task['last_run']; ?>
                        <tr>
                            <td><code class="sk-code"><?= e((string) $name) ?></code></td>
                            <td class="small text-muted">
                                <?= $last === null ? 'Never' : e(date('d M H:i', strtotime((string) $last['ran_at']))) ?>
                            </td>
                            <td>
                                <?php if ($last === null): ?>
                                    <span class="text-muted small">—</span>
                                <?php elseif ((string) $last['status'] === 'success'): ?>
                                    <span class="badge text-bg-success">ok</span>
                                <?php else: ?>
                                    <span class="badge text-bg-danger"><?= e((string) $last['status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <a class="btn btn-sm btn-outline-secondary mt-3" href="<?= e(url('admin/system/cron')) ?>">
                Manage scheduled tasks
            </a>
        </section>

        <section class="sk-panel">
            <h2 class="h6 mb-3">Recent logs</h2>
            <ul class="list-unstyled mb-3 d-grid gap-1 small">
                <?php foreach ($logs as $log): ?>
                    <li class="d-flex justify-content-between gap-2">
                        <a href="<?= e(url('admin/system/logs/view', ['file' => $log['file']])) ?>">
                            <?= e($log['channel']) ?> · <?= e($log['date']) ?>
                        </a>
                        <span class="text-muted"><?= number_format(((int) $log['size']) / 1024, 1) ?> KB</span>
                    </li>
                <?php endforeach; ?>
                <?php if ($logs === []): ?>
                    <li class="text-muted">No log files yet.</li>
                <?php endif; ?>
            </ul>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/system/logs')) ?>">All logs</a>
        </section>
    </div>

    <div class="col-12 col-xl-5">
        <section class="sk-panel mb-4">
            <h2 class="h6 mb-3">Health</h2>
            <p class="mb-3">
                <?php if ($latestHealth === null): ?>
                    <span class="text-muted">No health snapshot recorded yet.</span>
                <?php else: ?>
                    <?php $view->include('admin.partials.health-dot', ['status' => (string) $latestHealth['status']]); ?>
                    <span class="d-block small text-muted mt-1">
                        Recorded <?= e(date('d M Y H:i', strtotime((string) $latestHealth['checked_at']))) ?>
                    </span>
                <?php endif; ?>
            </p>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/system/health')) ?>">Run all checks</a>
        </section>

        <section class="sk-panel mb-4">
            <h2 class="h6 mb-3">Maintenance mode</h2>
            <p class="small text-muted">
                Visitors see a maintenance page; administrators keep full access. The updater turns this on
                automatically while it works.
            </p>
            <form method="post" action="<?= e(url('admin/system/maintenance')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="enabled" value="<?= $maintenance ? '0' : '1' ?>">
                <button class="btn btn-sm btn-<?= $maintenance ? 'success' : 'outline-warning' ?>" type="submit"
                        data-sk-confirm="<?= $maintenance ? 'Bring the site back online?' : 'Put the site into maintenance mode?' ?>">
                    <?= $maintenance ? 'Bring the site back online' : 'Enable maintenance mode' ?>
                </button>
            </form>
        </section>

        <section class="sk-panel mb-4">
            <h2 class="h6 mb-3">Backups</h2>
            <dl class="row small mb-3">
                <dt class="col-7 text-muted">Stored</dt>
                <dd class="col-5 text-end"><?= number_format((int) $backup['count']) ?></dd>
                <dt class="col-7 text-muted">Total size</dt>
                <dd class="col-5 text-end"><?= $mb((int) $backup['total_size']) ?></dd>
                <dt class="col-7 text-muted">Outside web root</dt>
                <dd class="col-5 text-end"><?= !empty($backup['outside_web_root']) ? 'Yes' : 'No' ?></dd>
            </dl>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/system/backups')) ?>">Manage backups</a>
        </section>

        <section class="sk-panel mb-4">
            <h2 class="h6 mb-3">Updates</h2>
            <dl class="row small mb-3">
                <dt class="col-7 text-muted">Source</dt>
                <dd class="col-5 text-end">
                    <?= $update['configured']
                        ? e((string) $update['repository'])
                        : '<span class="text-muted">Not set</span>' ?>
                </dd>
                <dt class="col-7 text-muted">Branch</dt>
                <dd class="col-5 text-end"><?= e((string) ($update['branch'] ?? '—')) ?></dd>
                <dt class="col-7 text-muted">Lock</dt>
                <dd class="col-5 text-end"><?= !empty($update['locked']) ? 'Held' : 'Free' ?></dd>
            </dl>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/system/update')) ?>">Check for updates</a>
        </section>

        <section class="sk-panel">
            <h2 class="h6 mb-3">Cache</h2>
            <p class="small text-muted">
                Entries: <?= number_format((int) ($meta['cache']['entries'] ?? 0)) ?>,
                <?= $mb((int) ($meta['cache']['bytes'] ?? 0)) ?>
            </p>
            <form method="post" action="<?= e(url('admin/system/cache/clear')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-secondary" type="submit">Clear cache</button>
            </form>
        </section>
    </div>
</div>
