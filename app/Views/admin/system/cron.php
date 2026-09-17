<?php
/**
 * @var array<string,array<string,mixed>> $tasks
 * @var array{cli:string,url:string,crontab:string,wget:string} $instructions
 * @var array<int,array<string,mixed>> $recent
 * @var array<string,string> $definitions
 */
$view->extend('layouts.admin');
?>
<div class="row g-4">
    <div class="col-12 col-xl-7">
        <section class="sk-panel mb-4">
            <h2 class="h6 mb-3">Tasks</h2>
            <div class="sk-table-wrap">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Task</th><th>Last run</th><th>Result</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($tasks as $name => $task): ?>
                        <?php $last = $task['last_run']; ?>
                        <tr>
                            <td>
                                <code class="sk-code"><?= e((string) $name) ?></code>
                                <span class="d-block small text-muted"><?= e((string) $task['description']) ?></span>
                            </td>
                            <td class="small text-muted text-nowrap">
                                <?= $last === null ? 'Never' : e(date('d M H:i', strtotime((string) $last['ran_at']))) ?>
                            </td>
                            <td>
                                <?php if ($last === null): ?>
                                    <span class="text-muted small">—</span>
                                <?php elseif ((string) $last['status'] === 'success'): ?>
                                    <span class="badge text-bg-success">ok</span>
                                <?php else: ?>
                                    <span class="badge text-bg-<?= (string) $last['status'] === 'skipped' ? 'secondary' : 'danger' ?>">
                                        <?= e((string) $last['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form method="post" action="<?= e(url('admin/system/cron/run')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="task" value="<?= e((string) $name) ?>">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit"
                                            data-sk-loading="Running…">Run</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form class="mt-3" method="post" action="<?= e(url('admin/system/cron/run')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="task" value="all">
                <button class="btn btn-sm btn-outline-primary" type="submit" data-sk-loading="Running…">
                    Run every task now
                </button>
            </form>
        </section>

        <section class="sk-panel">
            <h2 class="h6 mb-3">Recent runs</h2>
            <div class="sk-table-wrap">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>When</th><th>Task</th><th>Status</th><th class="text-end">ms</th><th class="d-none d-md-table-cell">Output</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $run): ?>
                        <tr>
                            <td class="small text-muted text-nowrap">
                                <?= e(date('d M H:i', strtotime((string) $run['ran_at']))) ?>
                            </td>
                            <td><code class="sk-code"><?= e((string) $run['task']) ?></code></td>
                            <td>
                                <span class="badge text-bg-<?= e(match ((string) $run['status']) {
                                    'success' => 'success', 'skipped' => 'secondary', default => 'danger',
                                }) ?>"><?= e((string) $run['status']) ?></span>
                            </td>
                            <td class="text-end small"><?= number_format((int) $run['duration_ms']) ?></td>
                            <td class="d-none d-md-table-cell small text-muted">
                                <?= e(mb_strimwidth((string) ($run['output'] ?? ''), 0, 70, '…')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recent === []): ?>
                        <tr><td colspan="5" class="text-muted small">No runs recorded yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-5">
        <section class="sk-panel">
            <h2 class="h6 mb-2">How to schedule it</h2>
            <p class="form-text mt-0">
                Add one of these to cPanel, aaPanel or crontab. The CLI form is preferred; the URL form exists for
                hosts without cron access and carries a secret token.
            </p>

            <?php foreach ([
                'Crontab (recommended)' => $instructions['crontab'],
                'CLI command'           => $instructions['cli'],
                'URL trigger'           => $instructions['url'],
                'wget'                  => $instructions['wget'],
            ] as $label => $command): ?>
                <div class="mb-3">
                    <label class="form-label small mb-1" for="cmd-<?= e(md5($label)) ?>"><?= e($label) ?></label>
                    <div class="input-group input-group-sm">
                        <input class="form-control font-monospace" type="text" readonly
                               id="cmd-<?= e(md5($label)) ?>" value="<?= e($command) ?>">
                        <button class="btn btn-outline-secondary" type="button"
                                data-sk-copy="#cmd-<?= e(md5($label)) ?>" data-sk-copy-message="Copied">
                            <i class="bi bi-clipboard" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

            <p class="form-text mb-0">
                Keep the URL token private: anyone holding it can trigger the scheduled tasks.
            </p>
        </section>
    </div>
</div>
