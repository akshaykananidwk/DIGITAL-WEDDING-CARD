<?php
/**
 * The 8-step wizard rail.
 *
 * @var int $step
 * @var array<string,mixed>|null $invitation
 */
$steps = [
    1 => ['label' => __('builder.step_category'), 'icon' => 'grid', 'url' => url('create')],
    2 => ['label' => __('builder.step_template'), 'icon' => 'palette', 'url' => url('create/templates')],
    3 => ['label' => __('builder.step_details'), 'icon' => 'pencil-square', 'url' => null],
    4 => ['label' => __('builder.step_photos'), 'icon' => 'images', 'url' => null],
    5 => ['label' => __('builder.step_design'), 'icon' => 'brush', 'url' => null],
    6 => ['label' => __('builder.step_preview'), 'icon' => 'eye', 'url' => null],
    7 => ['label' => __('builder.step_publish'), 'icon' => 'send', 'url' => null],
    8 => ['label' => __('builder.step_share'), 'icon' => 'share', 'url' => null],
];
$invitationId = isset($invitation) && is_array($invitation) ? (int) $invitation['id'] : 0;
?>
<nav class="sk-wizard" aria-label="<?= eattr(__('builder.step', ['current' => $step, 'total' => 8])) ?>">
    <ol class="sk-steps list-unstyled mb-0">
        <?php foreach ($steps as $number => $meta): ?>
            <?php
            $state = $number === $step ? 'is-current' : ($number < $step ? 'is-done' : '');
            $href = $meta['url'];
            if ($href === null && $invitationId > 0 && $number >= 3 && $number <= 7) {
                $href = url('builder/' . $invitationId, ['step' => $number]);
            }
            if ($href === null && $invitationId > 0 && $number === 8) {
                $href = url('builder/' . $invitationId . '/share');
            }
            ?>
            <li class="sk-step <?= e($state) ?>">
                <?php if ($href !== null && $number <= max($step, 2)): ?>
                    <a href="<?= e($href) ?>">
                        <span class="sk-step__num"><i class="bi bi-<?= e($meta['icon']) ?>" aria-hidden="true"></i></span>
                        <span class="sk-step__label"><?= e($meta['label']) ?></span>
                    </a>
                <?php else: ?>
                    <span aria-current="<?= $number === $step ? 'step' : 'false' ?>">
                        <span class="sk-step__num"><i class="bi bi-<?= e($meta['icon']) ?>" aria-hidden="true"></i></span>
                        <span class="sk-step__label"><?= e($meta['label']) ?></span>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
    <p class="sk-wizard__progress small text-muted mb-0">
        <?= e(__('builder.step', ['current' => $step, 'total' => 8])) ?>
    </p>
</nav>
