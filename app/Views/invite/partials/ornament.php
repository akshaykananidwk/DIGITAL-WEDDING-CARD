<?php
/**
 * Decorative ornaments, drawn as inline SVG.
 *
 * Inline and vector on purpose: nothing extra to download, sharp at any size,
 * and it inherits the template's colour through currentColor.
 *
 * @var string $name
 * @var string $size  sm|md|lg
 */

$name = $name ?? 'paisley';
$size = in_array($size ?? 'md', ['sm', 'md', 'lg'], true) ? $size : 'md';
?>
<span class="inv-ornament inv-ornament--<?= e($size) ?>" aria-hidden="true">
<?php if ($name === 'mandala'): ?>
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <circle cx="60" cy="20" r="11"/><circle cx="60" cy="20" r="6"/>
        <?php for ($i = 0; $i < 12; $i++): $a = $i * 30 * M_PI / 180; ?>
            <line x1="<?= 60 + cos($a) * 12 ?>" y1="<?= 20 + sin($a) * 12 ?>"
                  x2="<?= 60 + cos($a) * 17 ?>" y2="<?= 20 + sin($a) * 17 ?>"/>
        <?php endfor; ?>
        <path d="M2 20h30M88 20h30"/>
        <circle cx="36" cy="20" r="2.2" fill="currentColor" stroke="none"/>
        <circle cx="84" cy="20" r="2.2" fill="currentColor" stroke="none"/>
    </svg>
<?php elseif ($name === 'peacock'): ?>
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M60 36c-9-6-14-13-14-20a14 14 0 0 1 28 0c0 7-5 14-14 20z"/>
        <ellipse cx="60" cy="15" rx="4.5" ry="6"/><circle cx="60" cy="15" r="2" fill="currentColor" stroke="none"/>
        <path d="M6 24c10-8 20-8 30 0M84 24c10-8 20-8 30 0"/>
        <path d="M20 24c6-4 12-4 18 0M82 24c6-4 12-4 18 0"/>
    </svg>
<?php elseif ($name === 'floral'): ?>
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M4 22c14 0 22-10 26-16 4 6 12 16 26 16"/>
        <path d="M116 22c-14 0-22-10-26-16-4 6-12 16-26 16"/>
        <circle cx="60" cy="18" r="5"/><circle cx="60" cy="18" r="1.8" fill="currentColor" stroke="none"/>
        <path d="M52 30c4 4 12 4 16 0"/>
        <circle cx="34" cy="26" r="2" fill="currentColor" stroke="none"/>
        <circle cx="86" cy="26" r="2" fill="currentColor" stroke="none"/>
    </svg>
<?php elseif ($name === 'arch'): ?>
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M18 38V22a42 42 0 0 1 84 0v16"/>
        <path d="M28 38V24a32 32 0 0 1 64 0v14"/>
        <circle cx="60" cy="10" r="3.2" fill="currentColor" stroke="none"/>
        <path d="M2 38h116"/>
    </svg>
<?php elseif ($name === 'temple'): ?>
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M60 3l6 9h-12l6-9z" fill="currentColor" stroke="none"/>
        <path d="M44 38V18l16-9 16 9v20"/>
        <path d="M52 38V26h16v12"/>
        <path d="M6 38h28M86 38h28"/>
        <path d="M14 38V28h12v10M94 38V28h12v10"/>
    </svg>
<?php elseif ($name === 'lotus'): ?>
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M60 36c-6-4-9-10-9-16 3 2 6 6 9 12 3-6 6-10 9-12 0 6-3 12-9 16z"/>
        <path d="M60 36c-12-2-19-9-21-16 5 0 12 4 21 16z"/>
        <path d="M60 36c12-2 19-9 21-16-5 0-12 4-21 16z"/>
        <path d="M4 32h32M84 32h32"/>
    </svg>
<?php elseif ($name === 'leaf'): ?>
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M60 4c10 8 14 18 14 24 0 5-6 8-14 8s-14-3-14-8c0-6 4-16 14-24z"/>
        <path d="M60 8v26"/>
        <path d="M2 30h38M80 30h38"/>
    </svg>
<?php elseif ($name === 'star'): ?>
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M60 6l3.6 8.4L72 18l-8.4 3.6L60 30l-3.6-8.4L48 18l8.4-3.6L60 6z" fill="currentColor" stroke="none"/>
        <path d="M4 18h36M80 18h36"/>
        <circle cx="26" cy="18" r="1.6" fill="currentColor" stroke="none"/>
        <circle cx="94" cy="18" r="1.6" fill="currentColor" stroke="none"/>
    </svg>
<?php elseif ($name === 'line'): ?>
    <svg viewBox="0 0 120 12" fill="none" stroke="currentColor" stroke-width="1">
        <path d="M0 6h50M70 6h50"/>
        <path d="M60 2l4 4-4 4-4-4 4-4z" fill="currentColor" stroke="none"/>
    </svg>
<?php else: /* paisley */ ?>
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M60 4c8 5 12 12 12 18 0 7-5 12-12 12s-12-5-12-12c0-6 4-13 12-18z"/>
        <path d="M60 14c3 3 5 7 5 10s-2 5-5 5-5-2-5-5 2-7 5-10z"/>
        <path d="M2 28c12 0 22-4 30-10M118 28c-12 0-22-4-30-10"/>
        <circle cx="20" cy="26" r="2" fill="currentColor" stroke="none"/>
        <circle cx="100" cy="26" r="2" fill="currentColor" stroke="none"/>
    </svg>
<?php endif; ?>
</span>
