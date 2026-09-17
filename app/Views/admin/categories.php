<?php
/** @var array<int,array<string,mixed>> $tree */
$view->extend('layouts.admin');
?>
<div class="row g-4">
    <div class="col-12 col-xl-8">
        <?php foreach ($tree as $category): ?>
            <section class="sk-panel mb-3">
                <form class="row g-2 align-items-end" method="post"
                      action="<?= e(url('admin/categories/' . $category['id'])) ?>">
                    <?= csrf_field() ?>
                    <div class="col-12 col-sm-4">
                        <label class="form-label small mb-1" for="c-name-<?= (int) $category['id'] ?>">Category</label>
                        <input class="form-control form-control-sm" type="text" id="c-name-<?= (int) $category['id'] ?>"
                               name="name" required maxlength="120" value="<?= e($category['name']) ?>">
                    </div>
                    <div class="col-6 col-sm-3">
                        <label class="form-label small mb-1" for="c-icon-<?= (int) $category['id'] ?>">Icon</label>
                        <input class="form-control form-control-sm" type="text" id="c-icon-<?= (int) $category['id'] ?>"
                               name="icon" maxlength="40" value="<?= e((string) $category['icon']) ?>">
                    </div>
                    <div class="col-6 col-sm-2">
                        <label class="form-label small mb-1" for="c-order-<?= (int) $category['id'] ?>">Order</label>
                        <input class="form-control form-control-sm" type="number" id="c-order-<?= (int) $category['id'] ?>"
                               name="sort_order" value="<?= (int) $category['sort_order'] ?>">
                    </div>
                    <div class="col-6 col-sm-2">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" value="1" name="is_active"
                                   id="c-active-<?= (int) $category['id'] ?>"
                                <?= (int) $category['is_active'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="c-active-<?= (int) $category['id'] ?>">Active</label>
                        </div>
                    </div>
                    <div class="col-6 col-sm-1 d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary" type="submit" aria-label="Save">
                            <i class="bi bi-check2" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1" for="c-desc-<?= (int) $category['id'] ?>">Description</label>
                        <input class="form-control form-control-sm" type="text" id="c-desc-<?= (int) $category['id'] ?>"
                               name="description" maxlength="300" value="<?= e((string) $category['description']) ?>">
                    </div>
                </form>

                <hr>

                <div class="sk-table-wrap">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Subcategory</th><th>Slug</th><th class="text-end">Order</th><th>Active</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($category['subcategories'] as $sub): ?>
                            <tr>
                                <td colspan="5" class="p-1">
                                    <form class="row g-1 align-items-center" method="post"
                                          action="<?= e(url('admin/subcategories/' . $sub['id'])) ?>">
                                        <?= csrf_field() ?>
                                        <div class="col-12 col-sm-4">
                                            <input class="form-control form-control-sm" type="text" name="name"
                                                   required maxlength="120" value="<?= e($sub['name']) ?>"
                                                   aria-label="Subcategory name">
                                        </div>
                                        <div class="col-6 col-sm-3">
                                            <code class="sk-code"><?= e($sub['slug']) ?></code>
                                        </div>
                                        <div class="col-3 col-sm-2">
                                            <input class="form-control form-control-sm" type="number" name="sort_order"
                                                   value="<?= (int) $sub['sort_order'] ?>" aria-label="Sort order">
                                        </div>
                                        <div class="col-3 col-sm-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" value="1" name="is_active"
                                                       id="s-active-<?= (int) $sub['id'] ?>"
                                                    <?= (int) $sub['is_active'] === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="s-active-<?= (int) $sub['id'] ?>">On</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-1 d-flex gap-1">
                                            <button class="btn btn-sm btn-outline-primary" type="submit" aria-label="Save">
                                                <i class="bi bi-check2" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form class="row g-2 align-items-end mt-2" method="post" action="<?= e(url('admin/subcategories')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                    <div class="col-12 col-sm-6">
                        <label class="form-label small mb-1" for="new-sub-<?= (int) $category['id'] ?>">Add subcategory</label>
                        <input class="form-control form-control-sm" type="text" id="new-sub-<?= (int) $category['id'] ?>"
                               name="name" maxlength="120" placeholder="e.g. Simant / Kholo Bharvo">
                    </div>
                    <div class="col-12 col-sm-3">
                        <button class="btn btn-sm btn-outline-secondary w-100" type="submit">Add</button>
                    </div>
                </form>

                <?php if ($category['subcategories'] === []): ?>
                    <form class="mt-2" method="post" action="<?= e(url('admin/categories/' . $category['id'])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button class="btn btn-sm btn-outline-danger" type="submit"
                                data-sk-confirm="Delete this empty category?">Delete category</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="col-12 col-xl-4">
        <section class="sk-panel">
            <h2 class="h6 mb-3">New category</h2>
            <form method="post" action="<?= e(url('admin/categories')) ?>">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="new-name">Name</label>
                    <input class="form-control form-control-sm <?= error_for('name') ? 'is-invalid' : '' ?>"
                           type="text" id="new-name" name="name" required maxlength="120">
                    <?php if ($m = error_for('name')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="new-icon">Bootstrap icon name</label>
                    <input class="form-control form-control-sm" type="text" id="new-icon" name="icon"
                           maxlength="40" placeholder="stars">
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1" for="new-desc">Description</label>
                    <textarea class="form-control form-control-sm" id="new-desc" name="description"
                              rows="2" maxlength="300"></textarea>
                </div>
                <button class="btn btn-primary btn-sm w-100" type="submit">Create category</button>
            </form>
        </section>
    </div>
</div>
