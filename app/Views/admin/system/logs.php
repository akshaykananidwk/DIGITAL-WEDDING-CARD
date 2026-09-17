<?php
/** @var array<int,array<string,mixed>> $files */
$view->extend('layouts.admin');
?>
<p class="text-muted small">
    Logs are written outside the public web root and rotated by day. Passwords, tokens and API keys are never
    written to a log.
</p>

<div class="sk-panel">
    <div class="sk-table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Channel</th><th>Date</th><th class="text-end">Size</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td><code class="sk-code"><?= e((string) $file['channel']) ?></code></td>
                    <td class="small"><?= e((string) $file['date']) ?></td>
                    <td class="text-end small text-muted"><?= number_format(((int) $file['size']) / 1024, 1) ?> KB</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= e(url('admin/system/logs/view', ['file' => (string) $file['file']])) ?>">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($files === []): ?>
                <tr><td colspan="4" class="text-muted small">No log files yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
