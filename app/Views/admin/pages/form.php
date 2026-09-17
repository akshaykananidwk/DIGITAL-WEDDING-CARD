<?php
/** @var array<string,mixed>|null $page */
$view->extend('layouts.admin');
$isNew = $page === null;
$action = $isNew ? url('admin/pages') : url('admin/pages/' . $page['id']);
$value = static fn (string $key, string $default = '') => old($key, (string) ($page[$key] ?? $default));
?>
<form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <section class="sk-panel">
                <div class="mb-3">
                    <label class="form-label" for="title">Title</label>
                    <input class="form-control <?= error_for('title') ? 'is-invalid' : '' ?>" type="text" id="title"
                           name="title" required maxlength="191" value="<?= e($value('title')) ?>">
                    <?php if ($m = error_for('title')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="excerpt">Intro</label>
                    <textarea class="form-control" id="excerpt" name="excerpt" rows="2"
                              maxlength="500"><?= e($value('excerpt')) ?></textarea>
                </div>
                <div>
                    <label class="form-label" for="content">Content</label>
                    <textarea class="form-control font-monospace <?= error_for('content') ? 'is-invalid' : '' ?>"
                              id="content" name="content" rows="18"><?= e($value('content')) ?></textarea>
                    <?php if ($m = error_for('content')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                    <div class="form-text">
                        Basic HTML is allowed (headings, paragraphs, lists, links, tables). Scripts, iframes,
                        event attributes and inline styles are stripped when the page is saved.
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-4">
            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3">Publishing</h2>
                <div class="mb-3">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="published" <?= (string) ($page['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>
                            Published
                        </option>
                        <option value="draft" <?= (string) ($page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>
                            Draft
                        </option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="locale">Language</label>
                    <select class="form-select" id="locale" name="locale">
                        <?php foreach (['en' => 'English', 'gu' => 'ગુજરાતી', 'hi' => 'हिन्दी'] as $code => $label): ?>
                            <option value="<?= e($code) ?>" <?= (string) ($page['locale'] ?? 'en') === $code ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="show_in_footer" name="show_in_footer"
                        <?= (int) ($page['show_in_footer'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="show_in_footer">Link in the footer</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="show_in_header" name="show_in_header"
                        <?= (int) ($page['show_in_header'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="show_in_header">Link in the header</label>
                </div>
                <div>
                    <label class="form-label" for="sort_order">Sort order</label>
                    <input class="form-control" type="number" id="sort_order" name="sort_order"
                           value="<?= e($value('sort_order', '0')) ?>">
                </div>
            </section>

            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3">SEO</h2>
                <div class="mb-3">
                    <label class="form-label" for="meta_title">Meta title</label>
                    <input class="form-control" type="text" id="meta_title" name="meta_title"
                           maxlength="191" value="<?= e($value('meta_title')) ?>">
                </div>
                <div>
                    <label class="form-label" for="meta_description">Meta description</label>
                    <textarea class="form-control" id="meta_description" name="meta_description" rows="3"
                              maxlength="300"><?= e($value('meta_description')) ?></textarea>
                </div>
                <?php if (!$isNew): ?>
                    <p class="form-text mb-0 mt-2">
                        URL: <code class="sk-code">/page/<?= e((string) $page['slug']) ?></code>
                    </p>
                <?php endif; ?>
            </section>

            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit"><?= $isNew ? 'Create page' : 'Save page' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/pages')) ?>">Cancel</a>
            </div>
        </div>
    </div>
</form>
