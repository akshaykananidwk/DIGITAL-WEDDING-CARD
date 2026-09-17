<?php
/**
 * @var array<string,mixed> $state
 * @var array<string,mixed>|null $lastCheck
 * @var array<int,array<string,mixed>> $history
 * @var string $version
 * @var string $installedVersion
 * @var bool $canZip
 * @var bool $hasCurl
 */
$view->extend('layouts.admin');
$configured = (bool) $state['configured'];
$available = is_array($lastCheck) && !empty($lastCheck['update_available']);
$commit = is_array($lastCheck) ? ($lastCheck['commit'] ?? null) : null;
?>
<div class="row g-4">
    <div class="col-12 col-xl-7">
        <section class="sk-panel mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 mb-0">Current version</h2>
                <span class="badge text-bg-light text-dark">v<?= e($version) ?></span>
            </div>
            <dl class="row small mb-3">
                <dt class="col-5 text-muted">Files</dt>
                <dd class="col-7">v<?= e($version) ?></dd>
                <dt class="col-5 text-muted">Database schema</dt>
                <dd class="col-7">v<?= e($installedVersion) ?></dd>
                <dt class="col-5 text-muted">Repository</dt>
                <dd class="col-7">
                    <?= $configured ? e((string) $state['repository']) : '<span class="text-muted">Not configured</span>' ?>
                </dd>
                <dt class="col-5 text-muted">Branch</dt>
                <dd class="col-7"><?= e((string) ($state['branch'] ?? '—')) ?></dd>
                <dt class="col-5 text-muted">Access token</dt>
                <dd class="col-7">
                    <?php if (!empty($state['has_token'])): ?>
                        <code class="sk-code"><?= e((string) $state['masked_token']) ?></code>
                        <span class="d-block text-muted">Encrypted at rest. Never shown in full.</span>
                    <?php else: ?>
                        <span class="text-muted">None (public repository)</span>
                    <?php endif; ?>
                </dd>
            </dl>

            <?php if (!$hasCurl): ?>
                <div class="alert alert-warning small">
                    The cURL extension is not available. Updates will use PHP streams, which some hosts block.
                </div>
            <?php endif; ?>
            <?php if (!$canZip): ?>
                <div class="alert alert-warning small">
                    The Zip extension is not available. Install it before applying an update.
                </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2">
                <form method="post" action="<?= e(url('admin/system/update/check')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit" <?= $configured ? '' : 'disabled' ?>
                            data-sk-loading="Checking…">
                        <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Check for update
                    </button>
                </form>
                <form method="post" action="<?= e(url('admin/system/update/verify')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-secondary" type="submit" <?= $configured ? '' : 'disabled' ?>
                            data-sk-loading="Verifying…">Verify access</button>
                </form>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/system/update/history')) ?>">Update history</a>
            </div>
        </section>

        <?php if (is_array($lastCheck)): ?>
            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3">
                    <?= $available ? 'Update available' : 'Latest check' ?>
                </h2>
                <p class="small <?= $available ? '' : 'text-muted' ?>"><?= e((string) $lastCheck['message']) ?></p>

                <?php if (is_array($commit)): ?>
                    <dl class="row small mb-3">
                        <dt class="col-4 text-muted">Version</dt>
                        <dd class="col-8"><?= e((string) ($lastCheck['latest_version'] ?? '—')) ?></dd>
                        <dt class="col-4 text-muted">Commit</dt>
                        <dd class="col-8"><code class="sk-code"><?= e(substr((string) ($commit['sha'] ?? ''), 0, 10)) ?></code></dd>
                        <dt class="col-4 text-muted">Message</dt>
                        <dd class="col-8"><?= e((string) ($commit['message'] ?? '')) ?></dd>
                        <dt class="col-4 text-muted">Author</dt>
                        <dd class="col-8"><?= e((string) ($commit['author'] ?? '')) ?></dd>
                        <dt class="col-4 text-muted">Date</dt>
                        <dd class="col-8">
                            <?= $commit['date'] ?? null ? e(date('d M Y H:i', strtotime((string) $commit['date']))) : '—' ?>
                        </dd>
                        <?php if (isset($lastCheck['commits_ahead'])): ?>
                            <dt class="col-4 text-muted">Commits ahead</dt>
                            <dd class="col-8"><?= (int) $lastCheck['commits_ahead'] ?></dd>
                        <?php endif; ?>
                    </dl>
                <?php endif; ?>

                <?php $changed = is_array($lastCheck['changed_files'] ?? null) ? $lastCheck['changed_files'] : []; ?>
                <?php if ($changed !== []): ?>
                    <details class="mb-3">
                        <summary class="small fw-semibold"><?= count($changed) ?> changed files</summary>
                        <ul class="list-unstyled small mt-2 mb-0 d-grid gap-1" style="max-height:16rem;overflow:auto">
                            <?php foreach ($changed as $file): ?>
                                <li>
                                    <code class="sk-code">
                                        <?= e(is_array($file) ? (string) ($file['filename'] ?? '') : (string) $file) ?>
                                    </code>
                                    <?php if (is_array($file) && isset($file['status'])): ?>
                                        <span class="text-muted"><?= e((string) $file['status']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>

                <?php if ($available): ?>
                    <div class="alert alert-secondary small">
                        <strong>What happens when you update:</strong>
                        maintenance mode on → lock acquired → database and file backup →
                        download and validate the archive → stage and syntax-check it →
                        protected files preserved → atomic deploy → migrations → cache cleared →
                        health check. If any step fails, everything is rolled back automatically.
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <form method="post" action="<?= e(url('admin/system/update/dry-run')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-primary" type="submit" data-sk-loading="Running…">
                                <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>Dry run
                            </button>
                        </form>
                        <form method="post" action="<?= e(url('admin/system/update/apply')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-primary" type="submit" <?= $canZip ? '' : 'disabled' ?>
                                    data-sk-confirm="Apply this update now? The site goes into maintenance mode while it runs."
                                    data-sk-loading="Updating…">
                                <i class="bi bi-cloud-arrow-down me-1" aria-hidden="true"></i>Update now
                            </button>
                        </form>
                    </div>
                    <p class="form-text mb-0">
                        A dry run performs every step except the deploy, so you can confirm the archive is valid
                        before anything is written.
                    </p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!empty($state['locked'])): ?>
            <section class="sk-panel border-warning mb-4">
                <h2 class="h6 mb-2">Update lock is held</h2>
                <p class="small text-muted">
                    An update is running, or a previous run was interrupted. Only clear the lock if you are certain
                    no update is in progress.
                </p>
                <form method="post" action="<?= e(url('admin/system/update/unlock')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-warning" type="submit"
                            data-sk-confirm="Clear the update lock?">Clear the lock</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="sk-panel">
            <h2 class="h6 mb-3">Recent updates</h2>
            <div class="sk-table-wrap">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>When</th><th>Version</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $entry): ?>
                        <tr>
                            <td class="small text-muted text-nowrap">
                                <?= e(date('d M H:i', strtotime((string) $entry['created_at']))) ?>
                            </td>
                            <td class="small">
                                <?= e((string) ($entry['from_version'] ?? '?')) ?> →
                                <?= e((string) ($entry['to_version'] ?? '?')) ?>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= e(match ((string) $entry['status']) {
                                    'success' => 'success',
                                    'failed' => 'danger',
                                    'rolled_back' => 'warning',
                                    default => 'secondary',
                                }) ?>"><?= e(str_replace('_', ' ', (string) $entry['status'])) ?></span>
                            </td>
                            <td class="text-end">
                                <?php if ((string) $entry['status'] === 'success' && ($entry['backup_path'] ?? null) !== null): ?>
                                    <form method="post"
                                          action="<?= e(url('admin/system/update/rollback/' . $entry['id'])) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-warning" type="submit"
                                                data-sk-confirm="Roll back to the state captured before this update?">
                                            Roll back
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($history === []): ?>
                        <tr><td colspan="4" class="text-muted small">No updates have been applied yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-5">
        <section class="sk-panel mb-4">
            <h2 class="h6 mb-2">Update source</h2>
            <p class="form-text mt-0">
                Enter this once. After that every update is one click — no file uploads, ever.
            </p>
            <form method="post" action="<?= e(url('admin/system/update/source')) ?>">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="repository">Repository</label>
                    <input class="form-control form-control-sm <?= error_for('repository') ? 'is-invalid' : '' ?>"
                           type="text" id="repository" name="repository" required
                           placeholder="owner/repository"
                           value="<?= e((string) ($state['repository'] ?? '')) ?>">
                    <?php if ($m = error_for('repository')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="branch">Branch</label>
                    <input class="form-control form-control-sm" type="text" id="branch" name="branch"
                           value="<?= e((string) ($state['branch'] ?? 'main')) ?>" maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1" for="token">Personal access token</label>
                    <input class="form-control form-control-sm" type="password" id="token" name="token"
                           autocomplete="off"
                           placeholder="<?= !empty($state['has_token']) ? eattr((string) $state['masked_token']) : 'Only needed for a private repository' ?>">
                    <div class="form-text">
                        Needs read access to the repository contents and nothing else. It is encrypted with the
                        application key, kept out of the web root, never shown in full and never logged.
                        Leave blank to keep the stored token.
                    </div>
                </div>
                <button class="btn btn-sm btn-primary w-100" type="submit" data-sk-loading="Saving…">
                    Save and verify
                </button>
            </form>
        </section>

        <section class="sk-panel mb-4">
            <h2 class="h6 mb-2">Protected paths</h2>
            <p class="form-text mt-0">
                These are never overwritten or deleted by an update, and are restored untouched by a rollback.
            </p>
            <ul class="list-unstyled small mb-0 d-grid gap-1">
                <?php foreach ((array) $state['protected_paths'] as $path): ?>
                    <li><code class="sk-code"><?= e((string) $path) ?></code></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="sk-panel">
            <h2 class="h6 mb-2">Backup location</h2>
            <p class="small text-muted mb-0">
                <code class="sk-code d-block text-break"><?= e((string) $state['backup_directory']) ?></code>
                Every update writes a database and file backup here before touching anything.
            </p>
        </section>
    </div>
</div>
