<?php
/**
 * @var array<string,int> $stats
 * @var int $generated
 * @var int $palettes
 * @var int $layouts
 * @var int $subcategories
 * @var bool $enabled
 */
$view->extend('layouts.admin');
$capacity = $layouts * $palettes * max(1, $subcategories);
?>
<div class="row g-4">
    <div class="col-12 col-xl-7">
        <section class="sk-panel mb-4">
            <h2 class="h6 mb-2">Generate templates</h2>
            <p class="small text-muted">
                A template is a row, not a file: one of <?= (int) $layouts ?> layout renderers, crossed with
                <?= (int) $palettes ?> palettes, font pairings and <?= (int) $subcategories ?> occasions. The generator
                walks that space deterministically, so it never produces the same design twice and can keep going
                to <?= number_format($capacity) ?>+ distinct templates without a line of new code.
            </p>

            <?php if (!$enabled): ?>
                <div class="alert alert-warning small">
                    The <code>template_generator</code> feature flag is off.
                    <a href="<?= e(url('admin/flags')) ?>">Turn it on</a> to generate templates.
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('admin/templates-generate')) ?>">
                <?= csrf_field() ?>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-sm-4">
                        <label class="form-label small mb-1" for="count">How many</label>
                        <input class="form-control" type="number" id="count" name="count" min="1" max="10000" value="250">
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" value="1" id="activate" name="activate" checked>
                            <label class="form-check-label small" for="activate">Publish immediately</label>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <button class="btn btn-primary w-100" type="submit" <?= $enabled ? '' : 'disabled' ?>
                                data-sk-loading="Generating…">Generate</button>
                    </div>
                </div>
                <div class="form-text">
                    Large batches are written in chunks inside one transaction. 1,000 templates take a few seconds.
                </div>
            </form>
        </section>

        <section class="sk-panel">
            <h2 class="h6 mb-2">Remove generated templates</h2>
            <p class="small text-muted">
                Only templates created by the generator are removed, and only those no invitation is using.
                Hand-built templates are never touched.
            </p>
            <form method="post" action="<?= e(url('admin/templates-generate/remove')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger" type="submit"
                        data-sk-confirm="Remove every unused generated template?">
                    Remove <?= number_format($generated) ?> generated templates
                </button>
            </form>
        </section>
    </div>

    <div class="col-12 col-xl-5">
        <div class="row g-3">
            <div class="col-6"><?php $view->include('partials.stat-card', [
                'icon' => 'grid-3x3-gap', 'label' => 'Templates', 'value' => number_format($stats['total'])]); ?></div>
            <div class="col-6"><?php $view->include('partials.stat-card', [
                'icon' => 'magic', 'label' => 'Generated', 'value' => number_format($generated)]); ?></div>
            <div class="col-6"><?php $view->include('partials.stat-card', [
                'icon' => 'palette', 'label' => 'Palettes', 'value' => number_format($palettes)]); ?></div>
            <div class="col-6"><?php $view->include('partials.stat-card', [
                'icon' => 'layout-text-window', 'label' => 'Layouts', 'value' => number_format($layouts)]); ?></div>
        </div>

        <section class="sk-panel mt-3">
            <h2 class="h6 mb-2">Capacity</h2>
            <dl class="row small mb-0">
                <dt class="col-7 text-muted">Distinct combinations</dt>
                <dd class="col-5 text-end"><?= number_format($capacity) ?></dd>
                <dt class="col-7 text-muted">Occasions covered</dt>
                <dd class="col-5 text-end"><?= number_format($subcategories) ?></dd>
                <dt class="col-7 text-muted">Currently active</dt>
                <dd class="col-5 text-end"><?= number_format($stats['active']) ?></dd>
            </dl>
        </section>
    </div>
</div>
