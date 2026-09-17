<?php
/**
 * @var array<int,array<string,mixed>> $invitations
 * @var array<string,mixed> $pagination
 * @var array{status:string,q:string} $filters
 * @var array<string,int> $stats
 */
$view->extend('layouts.admin');
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'envelope-paper', 'label' => 'Invitations', 'value' => number_format($stats['total'])]); ?></div>
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'broadcast', 'label' => 'Published', 'value' => number_format($stats['published'])]); ?></div>
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'pencil', 'label' => 'Drafts', 'value' => number_format($stats['drafts'])]); ?></div>
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'eye', 'label' => 'Views', 'value' => number_format($stats['views'])]); ?></div>
</div>

<div class="sk-panel">
    <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('admin/invitations')) ?>">
        <div class="col-12 col-sm">
            <label class="form-label small mb-1" for="q">Search</label>
            <input class="form-control form-control-sm" type="search" id="q" name="q"
                   maxlength="80" value="<?= e((string) $filters['q']) ?>" placeholder="Title, slug or owner email">
        </div>
        <div class="col-6 col-sm-auto">
            <label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select form-select-sm" id="status" name="status" data-sk-auto-submit>
                <option value="">All</option>
                <?php foreach (['published', 'draft', 'unpublished', 'archived'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                        <?= e(ucfirst($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-auto">
            <button class="btn btn-sm btn-outline-secondary w-100" type="submit">Filter</button>
        </div>
    </form>

    <div class="sk-table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">Invitation</th>
                <th scope="col" class="d-none d-md-table-cell">Owner</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-end">Views</th>
                <th scope="col" class="text-end d-none d-sm-table-cell">RSVP</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($invitations as $invitation): ?>
                <?php $badge = match ((string) $invitation['status']) {
                    'published' => 'success', 'draft' => 'secondary', 'archived' => 'dark', default => 'warning',
                }; ?>
                <tr>
                    <td>
                        <a href="<?= e(url('admin/invitations/' . $invitation['id'])) ?>"><?= e($invitation['title']) ?></a>
                        <code class="sk-code d-block"><?= e('/invite/' . $invitation['slug']) ?></code>
                    </td>
                    <td class="d-none d-md-table-cell small">
                        <?= e((string) ($invitation['owner_name'] ?? '')) ?>
                        <span class="d-block text-muted"><?= e((string) ($invitation['owner_email'] ?? '')) ?></span>
                    </td>
                    <td><span class="badge text-bg-<?= e($badge) ?>"><?= e((string) $invitation['status']) ?></span></td>
                    <td class="text-end"><?= number_format((int) $invitation['view_count']) ?></td>
                    <td class="text-end d-none d-sm-table-cell"><?= number_format((int) $invitation['rsvp_count']) ?></td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= e(url('admin/invitations/' . $invitation['id'])) ?>" aria-label="Details">
                                <i class="bi bi-list-ul" aria-hidden="true"></i>
                            </a>
                            <?php if ((string) $invitation['status'] === 'published'): ?>
                                <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                                   href="<?= e(url('invite/' . $invitation['slug'])) ?>" aria-label="Open">
                                    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('admin/invitations/' . $invitation['id'] . '/status')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status"
                                       value="<?= (string) $invitation['status'] === 'published' ? 'unpublished' : 'published' ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit"
                                        data-sk-confirm="Change the status of this invitation?" aria-label="Toggle status">
                                    <i class="bi bi-<?= (string) $invitation['status'] === 'published' ? 'eye-slash' : 'broadcast' ?>"
                                       aria-hidden="true"></i>
                                </button>
                            </form>
                            <form method="post" action="<?= e(url('admin/invitations/' . $invitation['id'])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-outline-danger" type="submit"
                                        data-sk-confirm="Delete this invitation and its photos?" aria-label="Delete">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($invitations === []): ?>
                <tr><td colspan="6" class="text-muted small">No invitations match those filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
