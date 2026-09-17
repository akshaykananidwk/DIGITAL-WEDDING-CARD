<?php
/**
 * @var array<string,mixed> $template
 * @var array<string,mixed>|null $category
 * @var array<string,mixed>|null $subcategory
 * @var array<int,array<string,mixed>> $related
 * @var string $layoutName
 */
$view->extend('layouts.app');
$tags = is_array($template['tags'] ?? null) ? $template['tags'] : [];
$features = is_array($template['features'] ?? null) ? $template['features'] : [];
$previews = is_array($template['preview_images'] ?? null) ? $template['preview_images'] : [];
$thumb = (string) ($previews[0] ?? $template['thumbnail'] ?? '');
?>

<div class="container py-4">
    <nav aria-label="Breadcrumb" class="small mb-3">
        <a href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(url('templates')) ?>"><?= e(__('templates.title')) ?></a>
        <?php if ($category !== null): ?>
            <span aria-hidden="true">/</span>
            <a href="<?= e(url('category/' . $category['slug'])) ?>"><?= e($category['name']) ?></a>
        <?php endif; ?>
        <?php if ($subcategory !== null): ?>
            <span aria-hidden="true">/</span>
            <a href="<?= e(url('category/' . $category['slug'] . '/' . $subcategory['slug'])) ?>"><?= e($subcategory['name']) ?></a>
        <?php endif; ?>
    </nav>

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <div class="sk-preview-frame"
                 style="--sk-card-a:<?= eattr($template['color_primary']) ?>;--sk-card-b:<?= eattr($template['color_secondary']) ?>">
                <?php if ($thumb !== ''): ?>
                    <img src="<?= e(url($thumb)) ?>" alt="<?= eattr($template['name']) ?>" loading="eager" decoding="async">
                <?php else: ?>
                    <iframe src="<?= e(url('templates/' . $template['slug'] . '/preview')) ?>"
                            title="<?= eattr(__('templates.preview') . ': ' . $template['name']) ?>"
                            loading="lazy" referrerpolicy="same-origin"></iframe>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 mt-3">
                <a class="btn btn-outline-secondary flex-grow-1"
                   href="<?= e(url('templates/' . $template['slug'] . '/preview')) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-arrows-fullscreen me-1" aria-hidden="true"></i><?= e(__('templates.preview')) ?>
                </a>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="sk-panel">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php if ((int) $template['is_premium'] === 1): ?>
                        <span class="badge text-bg-warning"><?= e(__('templates.premium')) ?></span>
                    <?php else: ?>
                        <span class="badge text-bg-success"><?= e(__('templates.free')) ?></span>
                    <?php endif; ?>
                    <?php if ((int) $template['has_animation'] === 1): ?>
                        <span class="badge text-bg-dark"><?= e(__('templates.animated')) ?></span>
                    <?php endif; ?>
                    <span class="badge text-bg-light text-dark"><?= e(strtoupper((string) $template['language'])) ?></span>
                </div>

                <h1 class="h4 mb-1"><?= e($template['name']) ?></h1>
                <p class="text-muted small mb-3">
                    <?= e($layoutName) ?>
                    <span aria-hidden="true">·</span>
                    <?= (int) $template['page_count'] ?> <?= e(__('templates.pages')) ?>
                    <span aria-hidden="true">·</span>
                    <?= e((string) $template['orientation']) ?>
                </p>

                <?php if (($description = (string) ($template['description'] ?? '')) !== ''): ?>
                    <p class="mb-3"><?= e($description) ?></p>
                <?php endif; ?>

                <?php if (App\Core\Auth::check()): ?>
                    <form method="post" action="<?= e(url('create/start')) ?>" class="d-grid mb-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">
                        <button class="btn btn-primary btn-lg" type="submit" data-sk-loading="<?= eattr(__('common.loading')) ?>">
                            <i class="bi bi-magic me-1" aria-hidden="true"></i><?= e(__('templates.use_template')) ?>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="d-grid mb-3">
                        <a class="btn btn-primary btn-lg" href="<?= e(url('login')) ?>">
                            <i class="bi bi-magic me-1" aria-hidden="true"></i><?= e(__('templates.use_template')) ?>
                        </a>
                        <p class="form-text text-center mb-0"><?= e(__('auth.no_account')) ?>
                            <a href="<?= e(url('register')) ?>"><?= e(__('nav.register')) ?></a>
                        </p>
                    </div>
                <?php endif; ?>

                <dl class="row small mb-0">
                    <dt class="col-5 text-muted"><?= e(__('templates.uses')) ?></dt>
                    <dd class="col-7"><?= number_format((int) $template['use_count']) ?></dd>
                    <dt class="col-5 text-muted"><?= e(__('common.created')) ?></dt>
                    <dd class="col-7"><?= e(date('d M Y', strtotime((string) $template['created_at']))) ?></dd>
                    <dt class="col-5 text-muted">Code</dt>
                    <dd class="col-7"><code><?= e($template['code']) ?></code></dd>
                </dl>

                <?php if ($features !== []): ?>
                    <hr>
                    <ul class="list-unstyled small mb-0 d-grid gap-1">
                        <?php foreach ($features as $feature): ?>
                            <li><i class="bi bi-check2 text-success me-1" aria-hidden="true"></i><?= e((string) $feature) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($tags !== []): ?>
                    <hr>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($tags as $tag): ?>
                            <a class="sk-chip sk-chip--sm" href="<?= e(url('templates', ['tag' => (string) $tag])) ?>"><?= e($tag) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($related !== []): ?>
        <section class="mt-5">
            <h2 class="h5 mb-3"><?= e(__('templates.related')) ?></h2>
            <div class="row g-3">
                <?php foreach ($related as $item): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <?php $view->include('partials.template-card', ['template' => $item]); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
