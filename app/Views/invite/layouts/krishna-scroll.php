<?php
/**
 * Krishna Scroll.
 *
 * A soft gradient wash with a peacock motif and the wording set as a scroll.
 *
 * @var App\Services\TemplateContext $c
 */
?>
<div style="background:
        radial-gradient(700px 320px at 18% 0%, color-mix(in srgb, var(--inv-secondary) 26%, transparent), transparent 62%),
        radial-gradient(620px 300px at 88% 8%, color-mix(in srgb, var(--inv-primary) 20%, transparent), transparent 60%);">

    <section class="inv-hero">
        <div class="inv-page inv-framed">
            <?php $view->include('invite.partials.ornament', ['name' => $c->ornamentOr('peacock'), 'size' => 'lg']); ?>

            <?php if ($c->has('invocation')): ?>
                <p class="inv-hero__eyebrow mt-3 mb-1"><?= $c->get('invocation') ?></p>
            <?php endif; ?>

            <?php if ($c->has('groom_name') && $c->has('bride_name')): ?>
                <div class="inv-names mt-2">
                    <span class="inv-names__name"><?= $c->get('groom_name') ?></span>
                    <span class="inv-names__join"><?= e(__('pdf.weds')) ?></span>
                    <span class="inv-names__name"><?= $c->get('bride_name') ?></span>
                </div>
            <?php else: ?>
                <h1 class="inv-heading" style="font-size:2.6rem">
                    <?= $c->first('event_name', 'deity_name', 'celebrant_name') ?: $c->title() ?>
                </h1>
            <?php endif; ?>

            <?php if ($c->has('deity_name')): ?>
                <p class="inv-muted mt-2 mb-0"><?= $c->get('deity_name') ?></p>
            <?php endif; ?>

            <?php if ($c->eventTimestamp() > 0): ?>
                <p class="mt-3 mb-0" style="font-weight:600">
                    <?= $c->has('wedding_date') ? $c->longDate('wedding_date') : $c->longDate('event_date') ?>
                </p>
            <?php endif; ?>

            <?php if ($c->heroPhotoUrl() !== ''): ?>
                <img class="inv-hero__photo mt-4" src="<?= e($c->heroPhotoUrl()) ?>" alt="" loading="eager" decoding="async">
            <?php endif; ?>
        </div>
    </section>

    <?php $view->include('invite.partials.blessing', ['c' => $c]); ?>

    <?php if ($c->showSection('countdown') && (bool) $c->setting('show_countdown', true)): ?>
        <?php $view->include('invite.partials.countdown', ['c' => $c]); ?>
    <?php endif; ?>

    <?php $view->include('invite.partials.events', ['c' => $c]); ?>
    <?php $view->include('invite.partials.venue', ['c' => $c]); ?>
    <?php $view->include('invite.partials.gallery', ['c' => $c]); ?>
    <?php $view->include('invite.partials.family', ['c' => $c]); ?>
    <?php $view->include('invite.partials.contact', ['c' => $c]); ?>
    <?php $view->include('invite.partials.rsvp', ['c' => $c]); ?>
    <?php $view->include('invite.partials.share', ['c' => $c, 'share' => $share ?? (new App\Services\ShareService())->allLinks($c->invitation())]); ?>
</div>
