<?php
/** @var array<string,mixed> $page */
$view->extend('layouts.app');
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <article class="sk-prose">
                <h1 class="h3 mb-1"><?= e($page['title']) ?></h1>
                <p class="text-muted small mb-4">
                    <?= e(__('common.updated')) ?>
                    <?= e(date('d M Y', strtotime((string) ($page['updated_at'] ?: $page['created_at'])))) ?>
                </p>
                <?php if (($excerpt = (string) ($page['excerpt'] ?? '')) !== ''): ?>
                    <p class="lead"><?= e($excerpt) ?></p>
                <?php endif; ?>
                <?= (new App\Services\TemplateEngine())->sanitiseHtml((string) $page["content"]) ?>
            </article>
        </div>
    </div>
</div>
