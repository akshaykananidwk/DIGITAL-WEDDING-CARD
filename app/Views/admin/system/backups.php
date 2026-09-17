<?php
/**
 * @var array<int,array<string,mixed>> $backups
 * @var array<string,mixed> $pagination
 * @var array<string,mixed> $stats
 * @var bool $canZip
 */
$view->extend('layouts.admin');
$mb = static fn (int $bytes): string => $bytes <= 0 ? '—' : number_format($bytes / 1048576, 2) . ' MB';
?>
<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="sk-panel">
            <h2 class="h6 mb-3">Stored backups</h2>
            <div class="sk-table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th scope="col">Backup</th>
                        <th scope="col">Type</th>
                        <th scope="col" class="text-end">Size</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td>
                                <span class="d-block"><?= e((string) $backup['name']) ?></span>
                                <span class="small text-muted">
                                    <?= e(date('d M Y H:i', strtotime((string) $backup['created_at']))) ?>
                                    <?php if (($note = (string) ($backup['note'] ?? '')) !== ''): ?>
                                        · <?= e($note) ?>
                                    <?php endif; ?>
                                    <?php if (($version = (string) ($backup['app_version'] ?? '')) !== ''): ?>
                                        · v<?= e($version) ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td><span class="badge text-bg-light text-dark"><?= e((string) $backup['type']) ?></span></td>
                            <td class="text-end small"><?= $mb((int) $backup['size']) ?></td>
                            <td>
                                <span class="badge text-bg-<?= e(match ((string) $backup['status']) {
                                    'completed' => 'success', 'failed' => 'danger', default => 'secondary',
                                }) ?>"><?= e((string) $backup['status']) ?></span>
                                <?php if (($error = (string) ($backup['error_message'] ?? '')) !== ''): ?>
                                    <span class="d-block small text-danger"><?= e(mb_strimwidth($error, 0, 60, '…')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <?php if ((string) $backup['status'] === 'completed'): ?>
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="<?= e(url('admin/system/backups/' . $backup['id'] . '/download')) ?>"
                                           aria-label="Download">
                                            <i class="bi bi-download" aria-hidden="true"></i>
                                        </a>
                                        <form method="post"
                                              action="<?= e(url('admin/system/backups/' . $backup['id'] . '/verify')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-secondary" type="submit"
                                                    aria-label="Verify checksum">
                                                <i class="bi bi-shield-check" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                        <?php if (in_array((string) $backup['type'], ['database', 'full', 'update'], true)): ?>
                                            <form method="post"
                                                  action="<?= e(url('admin/system/backups/' . $backup['id'] . '/restore')) ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-warning" type="submit"
                                                        data-sk-confirm="Restore this backup? Current data in the restored tables is replaced."
                                                        aria-label="Restore">
                                                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <form method="post" action="<?= e(url('admin/system/backups/' . $backup['id'])) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"
                                                data-sk-confirm="Delete this backup file?" aria-label="Delete">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($backups === []): ?>
                        <tr><td colspan="5" class="text-muted small">No backups yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
    </div>

    <div class="col-12 col-xl-4">
        <section class="sk-panel mb-4">
            <h2 class="h6 mb-2">Create a backup</h2>
            <p class="form-text mt-0">
                Backups run in PHP with no shell access: the database is dumped through PDO in chunks and files are
                archived with ZipArchive<?= $canZip ? '' : ' (unavailable — PharData is used instead)' ?>.
            </p>
            <form method="post" action="<?= e(url('admin/system/backups')) ?>">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="type">What to include</label>
                    <select class="form-select form-select-sm" id="type" name="type">
                        <option value="database">Database only</option>
                        <option value="files">Uploads and storage only</option>
                        <option value="full">Everything</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1" for="note">Note</label>
                    <input class="form-control form-control-sm" type="text" id="note" name="note" maxlength="300"
                           placeholder="Before the December update">
                </div>
                <button class="btn btn-sm btn-primary w-100" type="submit" data-sk-loading="Backing up…">
                    Create backup
                </button>
            </form>
        </section>

        <section class="sk-panel">
            <h2 class="h6 mb-3">Storage</h2>
            <dl class="row small mb-0">
                <dt class="col-7 text-muted">Backups</dt>
                <dd class="col-5 text-end"><?= number_format((int) $stats['count']) ?></dd>
                <dt class="col-7 text-muted">Total size</dt>
                <dd class="col-5 text-end"><?= $mb((int) $stats['total_size']) ?></dd>
                <dt class="col-7 text-muted">Outside web root</dt>
                <dd class="col-5 text-end"><?= !empty($stats['outside_web_root']) ? 'Yes' : 'No' ?></dd>
            </dl>
            <p class="form-text mb-0">
                <code class="sk-code d-block text-break"><?= e((string) $stats['directory']) ?></code>
                The updater writes its own pre-update backup here and never deletes this directory.
            </p>
        </section>
    </div>
</div>
