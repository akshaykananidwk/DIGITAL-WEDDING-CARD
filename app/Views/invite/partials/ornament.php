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
<?php elseif ($name === 'ganesh'): ?>
    <!-- Shri Ganesh, suggested rather than drawn in detail: a trunk, an ear,
         a crown and a modak, which reads at 140px and stays respectful. -->
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M52 34c-4-3-6-7-6-11 0-8 6-14 14-14s14 6 14 14c0 4-2 8-6 11"/>
        <path d="M60 20c-3 2-4 5-4 8s2 6 5 7c2 1 4 0 4-2s-2-3-4-3"/>
        <path d="M46 16c-4 0-7 2-7 5s3 5 7 5M74 16c4 0 7 2 7 5s-3 5-7 5"/>
        <path d="M54 9l6-5 6 5"/><circle cx="60" cy="3.4" r="1.6" fill="currentColor" stroke="none"/>
        <circle cx="55" cy="17" r="1.3" fill="currentColor" stroke="none"/>
        <circle cx="65" cy="17" r="1.3" fill="currentColor" stroke="none"/>
        <path d="M4 24h30M86 24h30"/>
        <circle cx="38" cy="24" r="1.8" fill="currentColor" stroke="none"/>
        <circle cx="82" cy="24" r="1.8" fill="currentColor" stroke="none"/>
    </svg>
<?php elseif ($name === 'kalash'): ?>
    <!-- The kalash with a coconut and mango leaves that opens every ceremony. -->
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M52 36h16c2-5 3-9 3-13 0-5-4-8-11-8s-11 3-11 8c0 4 1 8 3 13z"/>
        <path d="M49 15h22"/>
        <path d="M60 15c0-4 2-6 5-7M60 15c0-4-2-6-5-7"/>
        <ellipse cx="60" cy="7" rx="4" ry="5"/>
        <path d="M6 26c10-6 18-6 26 0M88 26c10-6 18-6 26 0"/>
        <circle cx="36" cy="26" r="1.7" fill="currentColor" stroke="none"/>
        <circle cx="84" cy="26" r="1.7" fill="currentColor" stroke="none"/>
    </svg>
<?php elseif ($name === 'diya'): ?>
    <!-- A lit diya: flame, lamp, and a rangoli rule either side. -->
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M48 28c0 4 5 7 12 7s12-3 12-7z" fill="currentColor" stroke="none" opacity=".85"/>
        <path d="M46 28h28"/>
        <path d="M60 26c-3-2-4-4-4-7 0-4 4-6 4-11 0 5 4 7 4 11 0 3-1 5-4 7z" fill="currentColor" stroke="none"/>
        <path d="M4 30h34M82 30h34"/>
        <path d="M14 30c3-3 7-3 10 0M96 30c3-3 7-3 10 0"/>
    </svg>
<?php elseif ($name === 'shankh'): ?>
    <!-- The conch, blown at the auspicious moment. -->
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M50 32c-4-4-6-9-6-14 0-6 4-10 9-10 4 0 7 3 7 7 0 3-2 5-4 5"/>
        <path d="M54 8c6-3 12 0 15 6 3 7 1 14-4 18h-15"/>
        <path d="M56 20c3 0 5 2 5 5"/>
        <path d="M4 24h34M84 24h32"/>
        <circle cx="42" cy="24" r="1.6" fill="currentColor" stroke="none"/>
        <circle cx="80" cy="24" r="1.6" fill="currentColor" stroke="none"/>
    </svg>
<?php elseif ($name === 'flute'): ?>
    <!-- Krishna's bansuri with a peacock feather, for Vaishnav cards. -->
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M24 28h72" stroke-width="2.4"/>
        <circle cx="42" cy="28" r="1.2" fill="currentColor" stroke="none"/>
        <circle cx="52" cy="28" r="1.2" fill="currentColor" stroke="none"/>
        <circle cx="62" cy="28" r="1.2" fill="currentColor" stroke="none"/>
        <circle cx="72" cy="28" r="1.2" fill="currentColor" stroke="none"/>
        <path d="M84 28c6-10 14-16 22-18-4 8-10 14-16 17"/>
        <ellipse cx="102" cy="13" rx="3" ry="4.5" transform="rotate(30 102 13)"/>
        <path d="M4 28h14"/>
    </svg>
<?php elseif ($name === 'om'): ?>
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.4">
        <path d="M54 26c-5 0-9-3-9-7s4-7 8-7c3 0 5 2 5 4s-2 3-4 3"/>
        <path d="M58 16c4-3 9-2 11 2 2 4 0 8-4 9-2 .5-4 0-5-1"/>
        <path d="M66 10c3-2 7-1 8 2"/>
        <circle cx="76" cy="7" r="1.6" fill="currentColor" stroke="none"/>
        <path d="M70 5c2-2 5-2 7 0" stroke-width="1"/>
        <path d="M6 22h32M84 22h30"/>
    </svg>
<?php elseif ($name === 'swastik'): ?>
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.6">
        <path d="M60 8v24M48 20h24M60 8h-8M72 20v-8M60 32h8M48 20v8"/>
        <circle cx="52" cy="12" r="1.2" fill="currentColor" stroke="none"/>
        <circle cx="68" cy="28" r="1.2" fill="currentColor" stroke="none"/>
        <path d="M4 20h34M82 20h34" stroke-width="1"/>
    </svg>
<?php elseif ($name === 'garland'): ?>
    <!-- A marigold garland, the way it is strung across a doorway. -->
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1">
        <path d="M2 6c20 18 40 26 58 26s38-8 58-26"/>
        <?php for ($i = 0; $i <= 12; $i++): $t = $i / 12; $x = 2 + $t * 116;
              $y = 6 + (1 - (2 * $t - 1) ** 2) * 26; ?>
            <circle cx="<?= round($x, 1) ?>" cy="<?= round($y, 1) ?>" r="<?= $i % 2 === 0 ? 2.6 : 1.8 ?>"
                    fill="currentColor" stroke="none" opacity="<?= $i % 2 === 0 ? '.9' : '.6' ?>"/>
        <?php endfor; ?>
    </svg>
<?php elseif ($name === 'torana'): ?>
    <!-- The toran hung over a threshold: leaves and hanging beads. -->
    <svg viewBox="0 0 120 40" fill="none" stroke="currentColor" stroke-width="1.1">
        <path d="M2 8h116"/>
        <?php for ($i = 0; $i < 8; $i++): $x = 9 + $i * 14.6; ?>
            <path d="M<?= $x ?> 8c-3 6-3 12 0 17 3-5 3-11 0-17z" fill="currentColor" stroke="none" opacity=".75"/>
            <circle cx="<?= $x ?>" cy="<?= 28 + ($i % 2) * 4 ?>" r="1.7" fill="currentColor" stroke="none"/>
        <?php endfor; ?>
    </svg>
<?php elseif ($name === 'bandhani'): ?>
    <!-- Bandhani dots, the Gujarati tie-dye, as a band. -->
    <svg viewBox="0 0 120 24" fill="none" stroke="currentColor" stroke-width="1">
        <?php for ($row = 0; $row < 2; $row++): for ($i = 0; $i < 15; $i++):
              $x = 4 + $i * 8 + $row * 4; $y = 7 + $row * 9; ?>
            <circle cx="<?= $x ?>" cy="<?= $y ?>" r="2.2"/>
            <circle cx="<?= $x ?>" cy="<?= $y ?>" r=".8" fill="currentColor" stroke="none"/>
        <?php endfor; endfor; ?>
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
