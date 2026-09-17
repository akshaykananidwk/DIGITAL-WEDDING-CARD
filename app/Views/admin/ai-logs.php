<?php
/**
 * @var array<int,array<string,mixed>> $entries
 * @var array<string,mixed> $pagination
 * @var array<string,mixed> $filters
 * @var array{total:int,success:int,errors:int,tokens:int} $stats
 */
$view->extend('layouts.admin');
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'stars', 'label' => 'Calls · 30 days', 'value' => number_format($stats['total'])]); ?></div>
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'check2-circle', 'label' => 'Successful', 'value' => number_format($stats['success'])]); ?></div>
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'exclamation-triangle', 'label' => 'Errors', 'value' => number_format($stats['errors'])]); ?></div>
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'hash', 'label' => 'Tokens', 'value' => number_format($stats['tokens'])]); ?></div>
</div>

<div class="sk-panel">
    <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('admin/ai/logs')) ?>">
        <div class="col-6 col-sm-auto">
            <label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select form-select-sm" id="status" name="status" data-sk-auto-submit>
                <option value="">All</option>
                <?php foreach (['success', 'error', 'blocked', 'quota'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>>
                        <?= e(ucfirst($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-auto">
            <label class="form-label small mb-1" for="action">Action</label>
            <input class="form-control form-control-sm" type="text" id="action" name="action"
                   maxlength="40" value="<?= e((string) ($filters['action'] ?? '')) ?>">
        </div>
        <div class="col-12 col-sm-auto">
            <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
        </div>
        <div class="col-12 col-sm-auto ms-sm-auto">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/ai')) ?>">AI settings</a>
        </div>
    </form>

    <div class="sk-table-wrap">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">When</th>
                <th scope="col">Action</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-end d-none d-sm-table-cell">Tokens</th>
                <th scope="col" class="text-end d-none d-md-table-cell">ms</th>
                <th scope="col" class="d-none d-lg-table-cell">Note</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <?php $badge = match ((string) $entry['status']) {
                    'success' => 'success', 'quota' => 'warning', 'blocked' => 'warning', default => 'danger',
                }; ?>
                <tr>
                    <td class="text-nowrap small text-muted">
                        <?= e(date('d M H:i', strtotime((string) $entry['created_at']))) ?>
                    </td>
                    <td><code class="sk-code"><?= e((string) $entry['action']) ?></code></td>
                    <td><span class="badge text-bg-<?= e($badge) ?>"><?= e((string) $entry['status']) ?></span></td>
                    <td class="text-end d-none d-sm-table-cell"><?= number_format((int) ($entry['total_tokens'] ?? 0)) ?></td>
                    <td class="text-end d-none d-md-table-cell"><?= number_format((int) ($entry['latency_ms'] ?? 0)) ?></td>
                    <td class="d-none d-lg-table-cell small text-muted">
                        <?= e(mb_strimwidth((string) ($entry['error_message'] ?? ''), 0, 70, '…')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($entries === []): ?>
                <tr><td colspan="6" class="text-muted small">No AI calls recorded.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="form-text">
    Prompts and responses are not stored, only the action, status, token count and duration.
</p>

<?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
