<?php
/**
 * Celebration Pop.
 *
 * Bright and playful: birthdays, haldi, mehndi, sangeet and garba nights.
 *
 * @var App\Services\TemplateContext $c
 */
?>
<section class="inv-hero" style="padding-top:3rem;
        background:
            radial-gradient(circle at 15% 12%, color-mix(in srgb, var(--inv-secondary) 45%, transparent) 0 8px, transparent 9px),
            radial-gradient(circle at 82% 22%, color-mix(in srgb, var(--inv-primary) 35%, transparent) 0 6px, transparent 7px),
            radial-gradient(circle at 30% 82%, color-mix(in srgb, var(--inv-accent) 40%, transparent) 0 5px, transparent 6px),
            radial-gradient(circle at 70% 70%, color-mix(in srgb, var(--inv-secondary) 30%, transparent) 0 7px, transparent 8px);">
    <div class="inv-page">
        <div style="display:inline-block;padding:.4rem 1rem;border-radius:999px;
                    background:var(--inv-primary);color:#fff;font-size:.74rem;
                    letter-spacing:.16em;text-transform:uppercase;font-weight:700">
            <?= $c->has('event_name') ? $c->get('event_name') : e(__('invite.save_the_date')) ?>
        </div>

        <h1 class="inv-heading mt-3" style="font-size:clamp(2.4rem,10vw,3.8rem)">
            <?= $c->first('celebrant_name', 'groom_name', 'event_name') ?: $c->title() ?>
        </h1>

        <?php if ($c->has('age')): ?>
            <div style="display:inline-grid;place-items:center;width:4.5rem;height:4.5rem;border-radius:999px;
                        background:var(--inv-secondary);color:#3B2A00;margin-top:.75rem">
                <span class="inv-heading" style="font-size:2rem;color:inherit"><?= $c->get('age') ?></span>
            </div>
        <?php endif; ?>

        <?php if ($c->has('theme')): ?>
            <p class="inv-muted mt-3 mb-0">Theme: <?= $c->get('theme') ?></p>
        <?php endif; ?>

        <?php if ($c->eventTimestamp() > 0): ?>
            <p class="mt-3 mb-0" style="font-weight:700;font-size:1.05rem">
                <?= $c->longDate('event_date') ?>
                <?php if ($c->has('event_time')): ?>
                    · <?= $c->time('event_time') ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($c->has('artist_name')): ?>
            <p class="mt-3 mb-0">
                <span class="inv-detail__label d-block">Live</span>
                <strong><?= $c->get('artist_name') ?></strong>
            </p>
        <?php endif; ?>
    </div>
</section>

<?php $view->include('invite.partials.blessing', ['c' => $c]); ?>

<?php if ($c->has('entry_details')): ?>
    <section class="inv-section inv-section--tight inv-reveal">
        <div class="inv-page">
            <p class="inv-note inv-center mb-0"><?= $c->multiline('entry_details') ?></p>
        </div>
    </section>
<?php endif; ?>

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
