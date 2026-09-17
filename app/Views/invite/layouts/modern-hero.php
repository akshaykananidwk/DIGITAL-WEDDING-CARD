<?php
/**
 * Modern Hero.
 *
 * Photo-led, sans-serif, generous type. Works for weddings and business
 * openings alike.
 *
 * @var App\Services\TemplateContext $c
 */
$hero = $c->heroPhotoUrl();
?>
<section style="position:relative;min-height:<?= $hero !== '' ? '76vh' : 'auto' ?>;display:grid;place-items:end center;
        padding:3rem 0 2rem;overflow:hidden;
        <?= $hero !== '' ? 'background-image:linear-gradient(to top, rgba(0,0,0,.72), rgba(0,0,0,.15) 55%, rgba(0,0,0,.3)), url(\'' . e($hero) . '\');background-size:cover;background-position:center;' : '' ?>">
    <div class="inv-page inv-center" style="<?= $hero !== '' ? 'color:#fff' : '' ?>">
        <p class="inv-hero__eyebrow mb-2" style="<?= $hero !== '' ? 'color:rgba(255,255,255,.85)' : '' ?>">
            <?= $c->has('tagline') ? $c->get('tagline') : e(__('invite.save_the_date')) ?>
        </p>

        <h1 class="inv-heading" style="font-size:clamp(2.3rem,9vw,3.6rem);<?= $hero !== '' ? 'color:#fff' : '' ?>">
            <?php if ($c->has('groom_name') && $c->has('bride_name')): ?>
                <?= $c->get('groom_name') ?> &amp; <?= $c->get('bride_name') ?>
            <?php else: ?>
                <?= $c->first('business_name', 'event_name', 'celebrant_name') ?: $c->title() ?>
            <?php endif; ?>
        </h1>

        <?php if ($c->eventTimestamp() > 0): ?>
            <p class="mt-3 mb-0" style="font-weight:600;letter-spacing:.06em">
                <?= $c->has('wedding_date') ? $c->longDate('wedding_date')
                    : ($c->has('opening_date') ? $c->longDate('opening_date') : $c->longDate('event_date')) ?>
            </p>
        <?php endif; ?>

        <?php if ($c->has('venue')): ?>
            <p class="mt-1 mb-0" style="<?= $hero !== '' ? 'color:rgba(255,255,255,.85)' : 'color:var(--inv-muted)' ?>">
                <?= $c->get('venue') ?>
            </p>
        <?php endif; ?>

        <?php if ($c->has('chief_guest')): ?>
            <p class="mt-3 mb-0" style="font-size:.9rem">
                <span class="inv-detail__label">Chief guest</span><br>
                <strong><?= $c->get('chief_guest') ?></strong>
            </p>
        <?php endif; ?>
    </div>
</section>

<?php $view->include('invite.partials.blessing', ['c' => $c]); ?>

<?php if ($c->has('offer_text')): ?>
    <section class="inv-section inv-section--tight inv-reveal">
        <div class="inv-page inv-framed">
            <p class="inv-note inv-center mb-0"><?= $c->multiline('offer_text') ?></p>
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
