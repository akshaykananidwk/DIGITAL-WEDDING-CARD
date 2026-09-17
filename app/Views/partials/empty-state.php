<?php
/**
 * @var string $icon
 * @var string $title
 * @var string|null $text
 * @var string|null $actionUrl
 * @var string|null $actionLabel
 */
?>
<div class="sk-empty">
    <i class="bi bi-<?= e($icon ?? 'inbox') ?>" aria-hidden="true"></i>
    <h3 class="h5 mt-3 mb-1"><?= e($title) ?></h3>
    <?php if (!empty($text)): ?><p class="text-muted small mb-3"><?= e($text) ?></p><?php endif; ?>
    <?php if (!empty($actionUrl)): ?>
        <a class="btn btn-primary" href="<?= e($actionUrl) ?>"><?= e($actionLabel ?? __('common.continue')) ?></a>
    <?php endif; ?>
</div>
