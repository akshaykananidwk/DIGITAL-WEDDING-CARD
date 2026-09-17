<?php
/**
 * @var array<string,mixed>|null $user
 * @var array<int,array<string,mixed>> $roles
 */
$view->extend('layouts.admin');
$isNew = $user === null;
$action = $isNew ? url('admin/users') : url('admin/users/' . $user['id']);
?>
<div class="row">
    <div class="col-12 col-lg-8">
        <form class="sk-panel" method="post" action="<?= e($action) ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control <?= error_for('name') ? 'is-invalid' : '' ?>" type="text" id="name"
                           name="name" required maxlength="120"
                           value="<?= e(old('name', (string) ($user['name'] ?? ''))) ?>">
                    <?php if ($m = error_for('name')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control <?= error_for('email') ? 'is-invalid' : '' ?>" type="email" id="email"
                           name="email" required value="<?= e(old('email', (string) ($user['email'] ?? ''))) ?>">
                    <?php if ($m = error_for('email')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control <?= error_for('phone') ? 'is-invalid' : '' ?>" type="tel" id="phone"
                           name="phone" value="<?= e(old('phone', (string) ($user['phone'] ?? ''))) ?>">
                    <?php if ($m = error_for('phone')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="role_id">Role</label>
                    <select class="form-select <?= error_for('role_id') ? 'is-invalid' : '' ?>" id="role_id" name="role_id" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['id'] ?>"
                                <?= (int) ($user['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>>
                                <?= e($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($m = error_for('role_id')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="password">
                        Password <?= $isNew ? '' : '<span class="text-muted">(leave blank to keep)</span>' ?>
                    </label>
                    <input class="form-control <?= error_for('password') ? 'is-invalid' : '' ?>" type="password"
                           id="password" name="password" autocomplete="new-password" <?= $isNew ? 'required' : '' ?>>
                    <?php if ($m = error_for('password')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                    <div class="form-text">At least 8 characters, with a letter and a number.</div>
                </div>
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <?php foreach (['active', 'pending', 'suspended'] as $status): ?>
                            <option value="<?= e($status) ?>"
                                <?= (string) ($user['status'] ?? 'active') === $status ? 'selected' : '' ?>>
                                <?= e(ucfirst($status)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <button class="btn btn-primary" type="submit"><?= $isNew ? 'Create user' : 'Save changes' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/users')) ?>">Cancel</a>
            </div>
        </form>
    </div>

    <?php if (!$isNew): ?>
        <div class="col-12 col-lg-4">
            <section class="sk-panel">
                <h2 class="h6 mb-3">Account</h2>
                <dl class="row small mb-0">
                    <dt class="col-6 text-muted">Joined</dt>
                    <dd class="col-6"><?= e(date('d M Y', strtotime((string) $user['created_at']))) ?></dd>
                    <dt class="col-6 text-muted">Last login</dt>
                    <dd class="col-6">
                        <?= $user['last_login_at'] === null ? 'Never'
                            : e(date('d M Y H:i', strtotime((string) $user['last_login_at']))) ?>
                    </dd>
                    <dt class="col-6 text-muted">Email verified</dt>
                    <dd class="col-6"><?= ($user['email_verified_at'] ?? null) !== null ? 'Yes' : 'No' ?></dd>
                    <dt class="col-6 text-muted">Storage used</dt>
                    <dd class="col-6"><?= number_format(((int) ($user['storage_used'] ?? 0)) / 1048576, 1) ?> MB</dd>
                </dl>
            </section>
        </div>
    <?php endif; ?>
</div>
