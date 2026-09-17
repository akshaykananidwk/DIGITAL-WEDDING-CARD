<?php
/**
 * @var array<int,array<string,mixed>> $entries
 * @var array<string,mixed> $pagination
 * @var array<string,mixed> $filters
 * @var array<int,string> $actions
 */
$view->extend('layouts.admin');
?>
<p class="text-muted small">
    Every administrative change and every security-relevant event is recorded here. Entries are append-only and
    hold no passwords, tokens or raw IP addresses — only a salted hash of the address.
</p>

<div class="sk-panel">
    <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('admin/audit')) ?>">
        <div class="col-12 col-sm">
            <label class="form-label small mb-1" for="q">Search</label>
            <input class="form-control form-control-sm" type="search" id="q" name="q"
                   maxlength="80" value="<?= e((string) $filters['q']) ?>">
        </div>
        <div class="col-6 col-sm-auto">
            <label class="form-label small mb-1" for="action">Action</label>
            <select class="form-select form-select-sm" id="action" name="action" data-sk-auto-submit>
                <option value="">All</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= e($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>>
                        <?= e($action) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-3 col-sm-auto">
            <label class="form-label small mb-1" for="from">From</label>
            <input class="form-control form-control-sm" type="date" id="from" name="from"
                   value="<?= e((string) $filters['from']) ?>">
        </div>
        <div class="col-3 col-sm-auto">
            <label class="form-label small mb-1" for="to">To</label>
            <input class="form-control form-control-sm" type="date" id="to" name="to"
                   value="<?= e((string) $filters['to']) ?>">
        </div>
        <div class="col-6 col-sm-auto">
            <button class="btn btn-sm btn-outline-secondary w-100" type="submit">Filter</button>
        </div>
    </form>

    <div class="sk-table-wrap">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">When</th>
                <th scope="col">Action</th>
                <th scope="col">Actor</th>
                <th scope="col" class="d-none d-md-table-cell">Entity</th>
                <th scope="col" class="d-none d-lg-table-cell">Detail</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td class="text-nowrap small text-muted">
                        <?= e(date('d M Y H:i', strtotime((string) $entry['created_at']))) ?>
                    </td>
                    <td><code class="sk-code"><?= e((string) $entry['action']) ?></code></td>
                    <td class="small"><?= e((string) ($entry['actor_name'] ?? 'system')) ?></td>
                    <td class="d-none d-md-table-cell small text-muted">
                        <?= e((string) ($entry['entity_type'] ?? '')) ?>
                        <?= $entry['entity_id'] === null ? '' : '#' . (int) $entry['entity_id'] ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-muted">
                        <?= e(mb_strimwidth((string) ($entry['description'] ?? ''), 0, 90, '…')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($entries === []): ?>
                <tr><td colspan="5" class="text-muted small">Nothing recorded for those filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
