<?php
/**
 * @var array{status:string,checks:array<int,array<string,mixed>>,summary:array<string,int>,meta:array<string,mixed>} $result
 * @var array<string,mixed> $meta
 * @var array<int,array<string,mixed>> $history
 */
$view->extend('layouts.admin');
$icon = ['healthy' => 'check-circle-fill', 'warning' => 'exclamation-triangle-fill', 'critical' => 'x-octagon-fill'];
?>
<div class="row g-4">
    <div class="col-12 col-xl-8">
        <section class="sk-panel mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 mb-0">
                    <?php $view->include('admin.partials.health-dot', ['status' => (string) $result['status']]); ?>
                </h2>
                <div class="d-flex gap-2">
                    <span class="badge text-bg-success"><?= (int) ($result['summary']['healthy'] ?? 0) ?> ok</span>
                    <span class="badge text-bg-warning"><?= (int) ($result['summary']['warning'] ?? 0) ?> warnings</span>
                    <span class="badge text-bg-danger"><?= (int) ($result['summary']['critical'] ?? 0) ?> critical</span>
                </div>
            </div>

            <div class="sk-table-wrap">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                    <?php foreach ($result['checks'] as $check): ?>
                        <?php $status = (string) $check['status']; ?>
                        <tr>
                            <td style="width:2.5rem">
                                <i class="bi bi-<?= e($icon[$status] ?? 'question-circle') ?> text-<?= e(match ($status) {
                                    'healthy' => 'success', 'warning' => 'warning', default => 'danger',
                                }) ?>" aria-hidden="true"></i>
                                <span class="visually-hidden"><?= e($status) ?></span>
                            </td>
                            <td style="width:30%"><?= e((string) $check['label']) ?></td>
                            <td class="small text-muted"><?= e((string) $check['message']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form class="mt-3" method="post" action="<?= e(url('admin/system/health/run')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-primary" type="submit" data-sk-loading="Running…">
                    Run and record a snapshot
                </button>
            </form>
        </section>
    </div>

    <div class="col-12 col-xl-4">
        <section class="sk-panel">
            <h2 class="h6 mb-3">Recent snapshots</h2>
            <ul class="list-unstyled mb-0 d-grid gap-2 small">
                <?php foreach ($history as $entry): ?>
                    <li class="d-flex justify-content-between gap-2">
                        <?php $view->include('admin.partials.health-dot', [
                            'status' => (string) $entry['status'],
                            'label'  => date('d M H:i', strtotime((string) $entry['checked_at'])),
                        ]); ?>
                        <span class="text-muted"><?= e((string) ($entry['note'] ?? '')) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if ($history === []): ?>
                    <li class="text-muted">No snapshots recorded yet.</li>
                <?php endif; ?>
            </ul>
        </section>
    </div>
</div>
