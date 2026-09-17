<?php
/**
 * @var array<int,array<string,mixed>> $templates
 * @var array<string,mixed> $pagination
 * @var array<string,mixed> $filters
 * @var array<int,array<string,mixed>> $categories
 * @var array<string,int> $stats
 */
$view->extend('layouts.admin');
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'grid-3x3-gap', 'label' => 'Templates', 'value' => number_format($stats['total'])]); ?></div>
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'check2-circle', 'label' => 'Active', 'value' => number_format($stats['active'])]); ?></div>
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'star', 'label' => 'Premium', 'value' => number_format($stats['premium'])]); ?></div>
    <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
        'icon' => 'magic', 'label' => 'Animated', 'value' => number_format($stats['animated'])]); ?></div>
</div>

<div class="sk-panel">
    <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('admin/templates')) ?>">
        <div class="col-12 col-sm">
            <label class="form-label small mb-1" for="q">Search</label>
            <input class="form-control form-control-sm" type="search" id="q" name="q"
                   maxlength="80" value="<?= e((string) ($filters['q'] ?? '')) ?>">
        </div>
        <div class="col-6 col-sm-auto">
            <label class="form-label small mb-1" for="category">Category</label>
            <select class="form-select form-select-sm" id="category" name="category" data-sk-auto-submit>
                <option value="">All</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"
                        <?= (int) ($filters['category'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-auto">
            <label class="form-label small mb-1" for="language">Language</label>
            <select class="form-select form-select-sm" id="language" name="language" data-sk-auto-submit>
                <option value="">All</option>
                <?php foreach (['gu' => 'ગુજરાતી', 'hi' => 'हिन्दी', 'en' => 'English', 'multi' => 'Multi'] as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= ($filters['language'] ?? '') === $code ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-auto">
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" value="1" id="inactive" name="inactive"
                       data-sk-auto-submit <?= ($filters['active'] ?? true) === false ? 'checked' : '' ?>>
                <label class="form-check-label small" for="inactive">Include inactive</label>
            </div>
        </div>
        <div class="col-6 col-sm-auto">
            <button class="btn btn-sm btn-outline-secondary w-100" type="submit">Filter</button>
        </div>
        <div class="col-6 col-sm-auto d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/templates-generate')) ?>">
                <i class="bi bi-magic me-1" aria-hidden="true"></i>Generate
            </a>
            <a class="btn btn-sm btn-primary" href="<?= e(url('admin/templates/create')) ?>">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New
            </a>
        </div>
    </form>

    <div class="sk-table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">Template</th>
                <th scope="col" class="d-none d-md-table-cell">Layout</th>
                <th scope="col" class="d-none d-lg-table-cell">Language</th>
                <th scope="col" class="text-end">Used</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($templates as $template): ?>
                <tr>
                    <td>
                        <span class="d-inline-block rounded-1 me-2 align-middle"
                              style="width:14px;height:14px;background:<?= eattr($template['color_primary']) ?>"
                              aria-hidden="true"></span>
                        <a href="<?= e(url('admin/templates/' . $template['id'] . '/edit')) ?>"><?= e($template['name']) ?></a>
                        <code class="sk-code d-block"><?= e($template['code']) ?></code>
                    </td>
                    <td class="d-none d-md-table-cell small"><?= e((string) $template['layout_key']) ?></td>
                    <td class="d-none d-lg-table-cell small text-uppercase"><?= e((string) $template['language']) ?></td>
                    <td class="text-end"><?= number_format((int) $template['use_count']) ?></td>
                    <td>
                        <?php if ((int) $template['is_active'] === 1): ?>
                            <span class="badge text-bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Off</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= e(url('admin/templates/' . $template['id'] . '/preview')) ?>"
                               target="_blank" rel="noopener" aria-label="Preview">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= e(url('admin/templates/' . $template['id'] . '/fields')) ?>" aria-label="Fields">
                                <i class="bi bi-input-cursor-text" aria-hidden="true"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= e(url('admin/templates/' . $template['id'] . '/components')) ?>" aria-label="Components">
                                <i class="bi bi-layout-text-window" aria-hidden="true"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= e(url('admin/templates/' . $template['id'] . '/edit')) ?>" aria-label="Edit">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>
                            <form method="post" action="<?= e(url('admin/templates/' . $template['id'] . '/toggle')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-secondary" type="submit" aria-label="Toggle">
                                    <i class="bi bi-<?= (int) $template['is_active'] === 1 ? 'pause' : 'play' ?>" aria-hidden="true"></i>
                                </button>
                            </form>
                            <form method="post" action="<?= e(url('admin/templates/' . $template['id'] . '/duplicate')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-secondary" type="submit" aria-label="Duplicate">
                                    <i class="bi bi-files" aria-hidden="true"></i>
                                </button>
                            </form>
                            <form method="post" action="<?= e(url('admin/templates/' . $template['id'])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-outline-danger" type="submit"
                                        data-sk-confirm="Delete this template? Invitations already using it keep working."
                                        aria-label="Delete">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($templates === []): ?>
                <tr><td colspan="6" class="text-muted small">No templates match those filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
