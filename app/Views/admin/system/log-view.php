<?php
/**
 * @var string $file
 * @var string $content
 * @var int $lines
 * @var array<int,array<string,mixed>> $files
 */
$view->extend('layouts.admin');
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <nav class="small mb-0">
        <a href="<?= e(url('admin/system/logs')) ?>">Logs</a>
        <span aria-hidden="true">/</span>
        <code class="sk-code"><?= e($file) ?></code>
    </nav>
    <form class="d-flex gap-2 align-items-end" method="get" action="<?= e(url('admin/system/logs/view')) ?>">
        <input type="hidden" name="file" value="<?= e($file) ?>">
        <div>
            <label class="form-label small mb-1" for="lines">Lines</label>
            <select class="form-select form-select-sm" id="lines" name="lines" data-sk-auto-submit>
                <?php foreach ([100, 300, 1000, 3000] as $option): ?>
                    <option value="<?= $option ?>" <?= $lines === $option ? 'selected' : '' ?>><?= $option ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-sm btn-outline-secondary" type="submit">Reload</button>
    </form>
</div>

<div class="sk-panel">
    <pre class="sk-log mb-0"><?= e($content === '' ? 'This log is empty.' : $content) ?></pre>
</div>
