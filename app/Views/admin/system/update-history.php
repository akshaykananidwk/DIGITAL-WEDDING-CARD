<?php
/**
 * @var array<int,array<string,mixed>> $entries
 * @var array<string,mixed> $pagination
 */
$view->extend('layouts.admin');
?>
<nav class="small mb-3">
    <a href="<?= e(url('admin/system/update')) ?>">Updates</a>
    <span aria-hidden="true">/</span>
    <span class="text-muted">History</span>
</nav>

<div class="sk-panel">
    <div class="accordion" id="sk-update-history">
        <?php foreach ($entries as $entry): ?>
            <?php
            $id = (int) $entry['id'];
            $steps = is_array($entry['steps_log'] ?? null) ? $entry['steps_log'] : [];
            $changed = is_array($entry['changed_files'] ?? null) ? $entry['changed_files'] : [];
            $badge = match ((string) $entry['status']) {
                'success' => 'success', 'failed' => 'danger', 'rolled_back' => 'warning', default => 'secondary',
            };
            ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#update-<?= $id ?>" aria-expanded="false">
                        <span class="badge text-bg-<?= e($badge) ?> me-2"><?= e(str_replace('_', ' ', (string) $entry['status'])) ?></span>
                        <span class="me-2">
                            <?= e((string) ($entry['from_version'] ?? '?')) ?> → <?= e((string) ($entry['to_version'] ?? '?')) ?>
                        </span>
                        <span class="small text-muted">
                            <?= e(date('d M Y H:i', strtotime((string) $entry['created_at']))) ?>
                            <?php if ((int) $entry['duration_ms'] > 0): ?>
                                · <?= number_format(((int) $entry['duration_ms']) / 1000, 1) ?>s
                            <?php endif; ?>
                        </span>
                    </button>
                </h2>
                <div class="accordion-collapse collapse" id="update-<?= $id ?>" data-bs-parent="#sk-update-history">
                    <div class="accordion-body">
                        <dl class="row small">
                            <dt class="col-4 text-muted">Repository</dt>
                            <dd class="col-8"><?= e((string) ($entry['repository'] ?? '—')) ?>
                                (<?= e((string) ($entry['branch'] ?? '—')) ?>)</dd>
                            <dt class="col-4 text-muted">Commit</dt>
                            <dd class="col-8">
                                <code class="sk-code"><?= e(substr((string) ($entry['commit_sha'] ?? ''), 0, 10)) ?></code>
                                <?= e((string) ($entry['commit_message'] ?? '')) ?>
                            </dd>
                            <dt class="col-4 text-muted">Author</dt>
                            <dd class="col-8"><?= e((string) ($entry['commit_author'] ?? '—')) ?></dd>
                            <dt class="col-4 text-muted">Backup</dt>
                            <dd class="col-8">
                                <code class="sk-code text-break"><?= e((string) ($entry['backup_path'] ?? '—')) ?></code>
                            </dd>
                            <?php if (($error = (string) ($entry['error_message'] ?? '')) !== ''): ?>
                                <dt class="col-4 text-muted">Error</dt>
                                <dd class="col-8 text-danger"><?= e($error) ?></dd>
                            <?php endif; ?>
                        </dl>

                        <?php if ($steps !== []): ?>
                            <h3 class="h6 small text-uppercase text-muted">Steps</h3>
                            <ol class="list-unstyled small d-grid gap-1 mb-3">
                                <?php foreach ($steps as $step): ?>
                                    <li>
                                        <i class="bi bi-<?= !empty($step['ok']) ? 'check2 text-success' : 'x text-danger' ?>"
                                           aria-hidden="true"></i>
                                        <strong><?= e((string) ($step['step'] ?? '')) ?></strong>
                                        <span class="text-muted">— <?= e((string) ($step['message'] ?? '')) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>

                        <?php if ($changed !== []): ?>
                            <details>
                                <summary class="small fw-semibold"><?= count($changed) ?> changed files</summary>
                                <ul class="list-unstyled small mt-2 mb-0" style="max-height:14rem;overflow:auto">
                                    <?php foreach ($changed as $file): ?>
                                        <li><code class="sk-code">
                                            <?= e(is_array($file) ? (string) ($file['filename'] ?? '') : (string) $file) ?>
                                        </code></li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>

                        <?php if ((string) $entry['status'] === 'success' && ($entry['backup_path'] ?? null) !== null): ?>
                            <form class="mt-3" method="post"
                                  action="<?= e(url('admin/system/update/rollback/' . $id)) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-warning" type="submit"
                                        data-sk-confirm="Roll back to the state captured before this update?">
                                    Roll back to this point
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($entries === []): ?>
            <p class="text-muted small mb-0">No updates recorded yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
