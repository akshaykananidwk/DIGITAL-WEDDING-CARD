<?php
/**
 * @var array<int,array<string,mixed>> $roles
 * @var array<string,array<int,array<string,mixed>>> $permissions
 * @var array<int,array<int,string>> $granted role id => permission slugs
 */
$view->extend('layouts.admin');
?>
<p class="text-muted small">
    Permissions are checked on every admin route and on every write. A role with no permissions can
    sign in but cannot reach the panel.
</p>

<div class="accordion" id="sk-roles">
    <?php foreach ($roles as $index => $role): ?>
        <?php $roleGranted = $granted[(int) $role['id']] ?? []; ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button"
                        data-bs-toggle="collapse" data-bs-target="#role-<?= (int) $role['id'] ?>"
                        aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
                    <span class="me-2 fw-semibold"><?= e($role['name']) ?></span>
                    <span class="badge text-bg-light text-dark me-2">level <?= (int) $role['level'] ?></span>
                    <span class="small text-muted"><?= count($roleGranted) ?> permissions</span>
                </button>
            </h2>
            <div class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
                 id="role-<?= (int) $role['id'] ?>" data-bs-parent="#sk-roles">
                <div class="accordion-body">
                    <?php if ((string) $role['slug'] === 'super-admin'): ?>
                        <p class="alert alert-secondary small mb-0">
                            The super admin always holds every permission, including ones added by a future update.
                        </p>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('admin/roles/' . $role['id'] . '/permissions')) ?>">
                            <?= csrf_field() ?>
                            <?php foreach ($permissions as $group => $items): ?>
                                <fieldset class="mb-3">
                                    <legend class="form-label small text-uppercase text-muted"><?= e($group) ?></legend>
                                    <div class="sk-checks">
                                        <?php foreach ($items as $permission): ?>
                                            <?php $inputId = 'p-' . (int) $role['id'] . '-' . (int) $permission['id']; ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="<?= e($inputId) ?>"
                                                       name="permissions[]" value="<?= eattr($permission['slug']) ?>"
                                                    <?= in_array((string) $permission['slug'], $roleGranted, true) ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="<?= e($inputId) ?>">
                                                    <?= e($permission['name']) ?>
                                                    <code class="sk-code d-block"><?= e($permission['slug']) ?></code>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                            <?php endforeach; ?>
                            <button class="btn btn-primary btn-sm" type="submit">Save <?= e($role['name']) ?> permissions</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
