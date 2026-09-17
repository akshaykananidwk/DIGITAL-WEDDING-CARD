<?php
/** @var array<int,array<string,mixed>> $fonts */
$view->extend('layouts.admin');
$byScript = [];
foreach ($fonts as $font) {
    $byScript[(string) $font['script']][] = $font;
}
?>
<div class="row g-4">
    <div class="col-12 col-xl-8">
        <?php foreach ($byScript as $script => $group): ?>
            <section class="sk-panel mb-3">
                <h2 class="h6 mb-3"><?= e(ucfirst($script)) ?></h2>
                <div class="sk-table-wrap">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr><th>Font</th><th>PDF</th><th class="text-end">Order</th><th>Active</th><th>Default</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($group as $font): ?>
                            <tr>
                                <td colspan="6" class="p-1">
                                    <form class="row g-1 align-items-center" method="post"
                                          action="<?= e(url('admin/fonts/' . $font['id'])) ?>">
                                        <?= csrf_field() ?>
                                        <div class="col-12 col-sm-4">
                                            <input class="form-control form-control-sm" type="text" name="name"
                                                   value="<?= e($font['name']) ?>" maxlength="120" aria-label="Font name">
                                            <code class="sk-code"><?= e($font['family']) ?></code>
                                        </div>
                                        <div class="col-3 col-sm-2">
                                            <?php if ((int) $font['pdf_capable'] === 1): ?>
                                                <span class="badge text-bg-success">Embeddable</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary">Web only</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-3 col-sm-2">
                                            <input class="form-control form-control-sm" type="number" name="sort_order"
                                                   value="<?= (int) $font['sort_order'] ?>" aria-label="Sort order">
                                        </div>
                                        <div class="col-3 col-sm-1">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" value="1" name="is_active"
                                                       id="fa-<?= (int) $font['id'] ?>"
                                                    <?= (int) $font['is_active'] === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="fa-<?= (int) $font['id'] ?>">On</label>
                                            </div>
                                        </div>
                                        <div class="col-3 col-sm-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" value="1" name="is_default"
                                                       id="fd-<?= (int) $font['id'] ?>"
                                                    <?= (int) $font['is_default'] === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="fd-<?= (int) $font['id'] ?>">Default</label>
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
            </section>
        <?php endforeach; ?>
    </div>

    <div class="col-12 col-xl-4">
        <section class="sk-panel">
            <h2 class="h6 mb-2">Add a font</h2>
            <p class="form-text mt-0">
                TrueType (.ttf) fonts can be embedded in generated PDFs. OpenType (.otf) fonts work on the web only.
                Upload only fonts you are licensed to redistribute.
            </p>
            <form method="post" action="<?= e(url('admin/fonts')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="font">Font file</label>
                    <input class="form-control form-control-sm" type="file" id="font" name="font" required
                           accept=".ttf,.otf,font/ttf,font/otf">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="name">Display name</label>
                    <input class="form-control form-control-sm" type="text" id="name" name="name" maxlength="120">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="family">CSS family</label>
                    <input class="form-control form-control-sm" type="text" id="family" name="family" maxlength="60">
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1" for="script">Script</label>
                    <select class="form-select form-select-sm" id="script" name="script">
                        <?php foreach (['latin', 'gujarati', 'devanagari', 'multi'] as $script): ?>
                            <option value="<?= e($script) ?>"><?= e(ucfirst($script)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-sm btn-primary w-100" type="submit" data-sk-loading="Uploading…">Upload font</button>
            </form>
        </section>
    </div>
</div>
