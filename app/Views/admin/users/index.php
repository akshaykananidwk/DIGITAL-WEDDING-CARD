<?php
/**
 * @var array<int,array<string,mixed>> $users
 * @var array<string,mixed> $pagination
 * @var array<string,mixed> $filters
 * @var array<int,array<string,mixed>> $roles
 * @var array<string,int> $stats
 */
$view->extend('layouts.admin');
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'people', 'label' => 'Total', 'value' => number_format($stats['total'])]); ?></div>
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'person-check', 'label' => 'Active', 'value' => number_format($stats['active'])]); ?></div>
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'person-slash', 'label' => 'Suspended', 'value' => number_format($stats['suspended'])]); ?></div>
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'person-plus', 'label' => 'New · 7 days', 'value' => number_format($stats['new_7d'])]); ?></div>
</div>

<div class="sk-panel">
    <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('admin/users')) ?>">
        <div class="col-12 col-sm">
            <label class="form-label small mb-1" for="q">Search</label>
            <input class="form-control form-control-sm" type="search" id="q" name="q"
                   maxlength="80" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Name, email or phone">
        </div>
        <div class="col-6 col-sm-auto">
            <label class="form-label small mb-1" for="role">Role</label>
            <select class="form-select form-select-sm" id="role" name="role" data-sk-auto-submit>
                <option value="">All</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int) $role['id'] ?>"
                        <?= (int) ($filters['role'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>>
                        <?= e($role['name']) ?> (<?= (int) $role['user_count'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-auto">
            <label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select form-select-sm" id="status" name="status" data-sk-auto-submit>
                <option value="">All</option>
                <?php foreach (['active', 'pending', 'suspended'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>>
                        <?= e(ucfirst($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-auto">
            <button class="btn btn-sm btn-outline-secondary w-100" type="submit">Filter</button>
        </div>
        <div class="col-6 col-sm-auto">
            <a class="btn btn-sm btn-primary w-100" href="<?= e(url('admin/users/create')) ?>">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add user
            </a>
        </div>
    </form>

    <div class="sk-table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">User</th>
                <th scope="col">Role</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-end d-none d-md-table-cell">Invitations</th>
                <th scope="col" class="d-none d-lg-table-cell">Joined</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <?php $badge = match ((string) $user['status']) {
                    'active' => 'success', 'pending' => 'warning', default => 'secondary',
                }; ?>
                <tr>
                    <td>
                        <a href="<?= e(url('admin/users/' . $user['id'] . '/edit')) ?>"><?= e($user['name']) ?></a>
                        <span class="d-block small text-muted"><?= e($user['email']) ?></span>
                    </td>
                    <td class="small"><?= e((string) ($user['role_name'] ?? '')) ?></td>
                    <td><span class="badge text-bg-<?= e($badge) ?>"><?= e((string) $user['status']) ?></span></td>
                    <td class="text-end d-none d-md-table-cell"><?= number_format((int) ($user['invitation_count'] ?? 0)) ?></td>
                    <td class="d-none d-lg-table-cell small text-muted">
                        <?= e(date('d M Y', strtotime((string) $user['created_at']))) ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/users/' . $user['id'] . '/edit')) ?>"
                               aria-label="Edit"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                            <form method="post" action="<?= e(url('admin/users/' . $user['id'] . '/status')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status"
                                       value="<?= (string) $user['status'] === 'active' ? 'suspended' : 'active' ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit"
                                        data-sk-confirm="<?= (string) $user['status'] === 'active' ? 'Suspend this account?' : 'Reactivate this account?' ?>"
                                        aria-label="Toggle status">
                                    <i class="bi bi-<?= (string) $user['status'] === 'active' ? 'pause' : 'play' ?>" aria-hidden="true"></i>
                                </button>
                            </form>
                            <form method="post" action="<?= e(url('admin/users/' . $user['id'])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-outline-danger" type="submit"
                                        data-sk-confirm="Delete this user and everything they own?" aria-label="Delete">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($users === []): ?>
                <tr><td colspan="6" class="text-muted small">No users match those filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
