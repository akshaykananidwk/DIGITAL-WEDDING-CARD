<?php
/**
 * Business Launch.
 *
 * Professional and information first: what is opening, when, where, and who
 * to contact.
 *
 * @var App\Services\TemplateContext $c
 */
?>
<section class="inv-hero" style="padding-top:2.5rem">
    <div class="inv-page">
        <div class="inv-card" style="padding:1.75rem 1.35rem;text-align:center">
            <p class="inv-hero__eyebrow mb-2">
                <?= $c->has('event_name') ? $c->get('event_name') : 'Grand Opening' ?>
            </p>

            <h1 class="inv-heading" style="font-size:2.4rem">
                <?= $c->first('business_name', 'event_name') ?: $c->title() ?>
            </h1>

            <?php if ($c->has('tagline')): ?>
                <p class="inv-muted mt-1 mb-0"><?= $c->get('tagline') ?></p>
            <?php endif; ?>

            <?php $view->include('invite.partials.divider', ['c' => $c]); ?>

            <?php if ($c->eventTimestamp() > 0): ?>
                <p class="mb-1" style="font-weight:700;font-size:1.05rem">
                    <?= $c->longDate('opening_date') ?: $c->longDate('event_date') ?>
                </p>
                <?php if ($c->has('opening_time')): ?>
                    <p class="inv-muted mb-0">
                        Muhurat <?= $c->time('opening_time') ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($c->has('chief_guest')): ?>
                <div class="inv-note mt-3 mb-0">
                    <span class="inv-detail__label d-block mb-1">Chief guest</span>
                    <strong><?= $c->get('chief_guest') ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php $view->include('invite.partials.blessing', ['c' => $c]); ?>

<?php if ($c->has('offer_text')): ?>
    <section class="inv-section inv-section--tight inv-reveal">
        <div class="inv-page inv-center">
            <p class="inv-section__label">Opening offer</p>
            <p class="inv-note mb-0"><?= $c->multiline('offer_text') ?></p>
        </div>
    </section>
<?php endif; ?>

<?php if ($c->showSection('countdown') && (bool) $c->setting('show_countdown', true)): ?>
    <?php $view->include('invite.partials.countdown', ['c' => $c]); ?>
<?php endif; ?>

<?php $view->include('invite.partials.venue', ['c' => $c]); ?>
<?php $view->include('invite.partials.gallery', ['c' => $c]); ?>

<?php if ($c->has('proprietor') || $c->has('website')): ?>
    <section class="inv-section inv-section--tight inv-reveal">
        <div class="inv-page inv-center">
            <?php if ($c->has('proprietor')): ?>
                <p class="mb-2">
                    <span class="inv-detail__label d-block">Proprietor</span>
                    <strong><?= $c->get('proprietor') ?></strong>
                </p>
            <?php endif; ?>
            <?php if ($c->has('website')): ?>
                <a class="inv-btn inv-btn--ghost" href="<?= e((string) $c->raw('website')) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-globe" aria-hidden="true"></i><?= $c->get('website') ?>
                </a>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php $view->include('invite.partials.contact', ['c' => $c]); ?>
<?php $view->include('invite.partials.rsvp', ['c' => $c]); ?>
<?php $view->include('invite.partials.share', ['c' => $c, 'share' => $share ?? (new App\Services\ShareService())->allLinks($c->invitation())]); ?>
