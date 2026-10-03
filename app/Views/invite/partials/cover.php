<?php
/**
 * The opening: how the card arrives before a guest reads it.
 *
 * Four openings, chosen by the template's style pack - an envelope that
 * unseals, mandap doors that part, a patrika that unrolls, a printed card that
 * unfolds - and all four end the same way: the card turns to face the reader
 * and comes forward out of the screen.
 *
 * It is CSS 3D throughout: transforms and opacity only, no library, no images,
 * nothing that forces a layout. A phone can hold 60fps on it, and a reader who
 * asks for less motion, or who has turned the animation off, gets the card
 * immediately instead.
 *
 * @var App\Services\TemplateContext $c
 */

$opening = $c->style('opening');
$skip = $c->setting('skip_animation', false);

$groom = $c->first('groom_name', 'celebrant_name', 'child_name', 'business_name');
$bride = $c->get('bride_name');
$title = $groom !== '' && $bride !== '' ? $groom . '<span class="inv-cover__amp">&amp;</span>' . $bride : $c->title();
$date = $c->has('wedding_date') ? $c->longDate('wedding_date') : $c->longDate('event_date');
$seal = mb_substr(trim((string) strip_tags($groom !== '' ? $groom : $c->title())), 0, 1);
?>
<div class="inv-cover" data-inv-cover data-opening="<?= e($opening) ?>" data-inv-skip="<?= $skip ? '1' : '0' ?>">
    <button type="button" class="inv-cover__skip" data-inv-skip-button>
        <?= e(__('invite.skip_animation')) ?>
    </button>

    <div class="inv-stage">
        <?php if ($opening === 'doors'): ?>
            <!-- Two carved doors, as at the entrance to a mandap, which part
                 to reveal the card standing behind them. -->
            <div class="inv-open inv-open--doors" data-inv-open role="button" tabindex="0"
                 aria-label="<?= eattr(__('invite.open_invitation')) ?>">
                <div class="inv-open__scene">
                    <div class="inv-door inv-door--left">
                        <span class="inv-door__ring"></span>
                    </div>
                    <div class="inv-door inv-door--right">
                        <span class="inv-door__ring"></span>
                    </div>
                    <div class="inv-letter">
                        <?php $view->include('invite.partials.ornament', ['name' => $c->ornament(), 'size' => 'sm']); ?>
                        <p class="inv-letter__names"><?= $title ?></p>
                        <?php if ($date !== ''): ?><p class="inv-letter__date"><?= $date ?></p><?php endif; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($opening === 'scroll'): ?>
            <!-- A patrika: the rolled scroll unrolls from its rods. -->
            <div class="inv-open inv-open--scroll" data-inv-open role="button" tabindex="0"
                 aria-label="<?= eattr(__('invite.open_invitation')) ?>">
                <div class="inv-open__scene">
                    <span class="inv-scroll__rod inv-scroll__rod--top"></span>
                    <div class="inv-letter inv-letter--scroll">
                        <?php $view->include('invite.partials.ornament', ['name' => $c->ornament(), 'size' => 'sm']); ?>
                        <p class="inv-letter__names"><?= $title ?></p>
                        <?php if ($date !== ''): ?><p class="inv-letter__date"><?= $date ?></p><?php endif; ?>
                    </div>
                    <span class="inv-scroll__rod inv-scroll__rod--bottom"></span>
                </div>
            </div>

        <?php elseif ($opening === 'fold'): ?>
            <!-- A printed card: the two wings fold open. -->
            <div class="inv-open inv-open--fold" data-inv-open role="button" tabindex="0"
                 aria-label="<?= eattr(__('invite.open_invitation')) ?>">
                <div class="inv-open__scene">
                    <div class="inv-letter">
                        <?php $view->include('invite.partials.ornament', ['name' => $c->ornament(), 'size' => 'sm']); ?>
                        <p class="inv-letter__names"><?= $title ?></p>
                        <?php if ($date !== ''): ?><p class="inv-letter__date"><?= $date ?></p><?php endif; ?>
                    </div>
                    <div class="inv-wing inv-wing--left"></div>
                    <div class="inv-wing inv-wing--right"></div>
                    <!-- One motif centred on the closed cover, across the fold,
                         the way it is printed on a real kankotri. It belongs to
                         the cover rather than to either wing, so it sits above
                         both and leaves with them. -->
                    <span class="inv-fold__motif">
                        <?php $view->include('invite.partials.ornament', ['name' => $c->ornament(), 'size' => 'md']); ?>
                    </span>
                </div>
            </div>

        <?php else: /* envelope */ ?>
            <div class="inv-open inv-open--envelope" data-inv-open role="button" tabindex="0"
                 aria-label="<?= eattr(__('invite.open_invitation')) ?>">
                <div class="inv-open__scene">
                    <div class="inv-letter">
                        <?php $view->include('invite.partials.ornament', ['name' => $c->ornament(), 'size' => 'sm']); ?>
                        <p class="inv-letter__names"><?= $title ?></p>
                        <?php if ($date !== ''): ?><p class="inv-letter__date"><?= $date ?></p><?php endif; ?>
                    </div>
                    <div class="inv-envelope__back"></div>
                    <div class="inv-envelope__front"></div>
                    <div class="inv-envelope__flap"></div>
                    <span class="inv-envelope__seal"><?= e($seal !== '' ? $seal : 'શ') ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="inv-cover__caption">
            <p class="inv-hero__eyebrow mb-1"><?= e(__('invite.save_the_date')) ?></p>
            <h1 class="inv-cover__title"><?= $title ?></h1>
            <?php if ($date !== ''): ?>
                <p class="inv-muted mt-1 mb-3"><?= $date ?></p>
            <?php endif; ?>
            <button type="button" class="inv-btn" data-inv-open>
                <?= e(__('invite.tap_to_open')) ?>
                <span class="inv-btn__arrow" aria-hidden="true">→</span>
            </button>
        </div>
    </div>
</div>
