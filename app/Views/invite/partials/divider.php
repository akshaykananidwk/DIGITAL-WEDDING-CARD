<?php
/**
 * The rule between two parts of a card.
 *
 * A separate partial because the divider is one of the style axes: the same
 * layout with a paisley chain reads differently from one with a plain double
 * rule, and that difference is most of what makes two cards look unalike.
 *
 * @var App\Services\TemplateContext $c
 * @var string|null $style override; normally the template's own axis
 */

$style = $style ?? $c->style('divider');
?>
<?php if ($style === 'paisley'): ?>
    <div class="inv-divider inv-divider--paisley" aria-hidden="true">
        <svg viewBox="0 0 200 16" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M0 8h70M130 8h70"/>
            <?php foreach ([88, 100, 112] as $i => $x): ?>
                <path d="M<?= $x ?> 3c2.6 1.7 4 4 4 6 0 2.3-1.8 4-4 4s-4-1.7-4-4c0-2 1.4-4.3 4-6z"
                      <?= $i === 1 ? 'stroke-width="1.4"' : 'opacity=".7"' ?>/>
            <?php endforeach; ?>
        </svg>
    </div>
<?php elseif ($style === 'dots'): ?>
    <div class="inv-divider inv-divider--dots" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span>
    </div>
<?php elseif ($style === 'swag'): ?>
    <div class="inv-divider inv-divider--swag" aria-hidden="true">
        <svg viewBox="0 0 200 22" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M4 4c30 16 62 16 96 16s66 0 96-16"/>
            <?php for ($i = 1; $i < 10; $i++): $t = $i / 10; $x = 4 + $t * 192;
                  $y = 4 + (1 - (2 * $t - 1) ** 2) * 16; ?>
                <circle cx="<?= round($x, 1) ?>" cy="<?= round($y, 1) ?>" r="1.7" fill="currentColor" stroke="none"/>
            <?php endfor; ?>
        </svg>
    </div>
<?php elseif ($style === 'double'): ?>
    <div class="inv-divider inv-divider--double" aria-hidden="true"></div>
<?php elseif ($style === 'chevron'): ?>
    <div class="inv-divider inv-divider--chevron" aria-hidden="true">
        <svg viewBox="0 0 200 14" fill="none" stroke="currentColor" stroke-width="1.1">
            <path d="M0 7h74M126 7h74"/>
            <path d="M84 11l8-4-8-4M116 3l-8 4 8 4"/>
            <path d="M100 2.5l4 4.5-4 4.5-4-4.5 4-4.5z" fill="currentColor" stroke="none"/>
        </svg>
    </div>
<?php elseif ($style === 'knot'): ?>
    <div class="inv-divider inv-divider--knot" aria-hidden="true">
        <svg viewBox="0 0 200 18" fill="none" stroke="currentColor" stroke-width="1.1">
            <path d="M0 9h76M124 9h76"/>
            <path d="M88 9c0-4 3-6 6-6s6 2 6 6-3 6-6 6-6-2-6-6z"/>
            <path d="M100 9c0-4 3-6 6-6s6 2 6 6-3 6-6 6-6-2-6-6z"/>
        </svg>
    </div>
<?php elseif ($style === 'leafline'): ?>
    <div class="inv-divider inv-divider--leafline" aria-hidden="true">
        <svg viewBox="0 0 200 18" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M0 9h68M132 9h68"/>
            <path d="M100 2c-4 3-6 5-6 7s2 5 6 7c4-2 6-5 6-7s-2-4-6-7z" fill="currentColor" stroke="none" opacity=".85"/>
            <path d="M74 9c4-3 8-3 12 0-4 3-8 3-12 0zM126 9c-4-3-8-3-12 0 4 3 8 3 12 0z"/>
        </svg>
    </div>
<?php else: /* diamond - the original */ ?>
    <div class="inv-rule"><span class="inv-rule__diamond"></span></div>
<?php endif; ?>
