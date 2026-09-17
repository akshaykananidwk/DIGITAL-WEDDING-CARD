<?php
/** @var array<int,array<string,mixed>> $pages */
$view->extend('layouts.admin');
?>
<div class="sk-panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0"><?= count($pages) ?> pages</h2>
        <a class="btn btn-sm btn-primary" href="<?= e(url('admin/pages/create')) ?>">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New page
        </a>
    </div>

    <div class="sk-table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">Title</th>
                <th scope="col" class="d-none d-md-table-cell">Slug</th>
                <th scope="col">Status</th>
                <th scope="col" class="d-none d-sm-table-cell">Placement</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pages as $page): ?>
                <tr>
                    <td><a href="<?= e(url('admin/pages/' . $page['id'] . '/edit')) ?>"><?= e($page['title']) ?></a></td>
                    <td class="d-none d-md-table-cell"><code class="sk-code">/page/<?= e($page['slug']) ?></code></td>
                    <td>
                        <?php if ((string) $page['status'] === 'published'): ?>
                            <span class="badge text-bg-success">Published</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-sm-table-cell small text-muted">
                        <?= (int) $page['show_in_footer'] === 1 ? 'Footer' : '' ?>
                        <?= (int) $page['show_in_header'] === 1 ? 'Header' : '' ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                               href="<?= e(url('page/' . $page['slug'])) ?>" aria-label="View">
                                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= e(url('admin/pages/' . $page['id'] . '/edit')) ?>" aria-label="Edit">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>
                            <form method="post" action="<?= e(url('admin/pages/' . $page['id'])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-outline-danger" type="submit"
                                        data-sk-confirm="Delete this page?" aria-label="Delete">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($pages === []): ?>
                <tr><td colspan="5" class="text-muted small">No pages yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
