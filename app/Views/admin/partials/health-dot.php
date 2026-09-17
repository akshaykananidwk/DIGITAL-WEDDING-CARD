<?php
/**
 * @var string $status healthy|warning|critical
 * @var string|null $label
 */
$status = $status ?? 'warning';
$class = in_array($status, ['healthy', 'warning', 'critical'], true) ? $status : 'warning';
$text = ['healthy' => 'Healthy', 'warning' => 'Needs attention', 'critical' => 'Critical'][$class];
?>
<span class="d-inline-flex align-items-center gap-2">
    <span class="sk-health-dot sk-health-dot--<?= e($class) ?>" aria-hidden="true"></span>
    <span><?= e($label ?? $text) ?></span>
</span>
