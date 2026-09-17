<?php
/**
 * @var string $icon
 * @var string $label
 * @var string $value
 * @var string|null $hint
 * @var string|null $href
 */
?>
<div class="sk-stat h-100">
    <span class="sk-stat__icon" aria-hidden="true"><i class="bi bi-<?= e($icon) ?>"></i></span>
    <div>
        <div class="sk-stat__value"><?= e($value) ?></div>
        <div class="sk-stat__label"><?= e($label) ?></div>
        <?php if (!empty($hint)): ?><div class="sk-stat__hint"><?= e($hint) ?></div><?php endif; ?>
    </div>
    <?php if (!empty($href)): ?>
        <a class="sk-stat__link" href="<?= e($href) ?>" aria-label="<?= eattr($label) ?>">
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
    <?php endif; ?>
</div>
