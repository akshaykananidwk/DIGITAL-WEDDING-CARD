<?php
/**
 * Royal Arch.
 *
 * A tall arch frames the names; deliberately formal and symmetrical.
 *
 * @var App\Services\TemplateContext $c
 */
?>
<section class="inv-hero" style="padding-bottom:0">
    <div class="inv-page">
        <div style="position:relative;border:1px solid var(--inv-secondary);
                    border-radius:50% 50% 12px 12px / 22% 22% 3% 3%;
                    padding:2.75rem 1.4rem 2rem;background:var(--inv-surface);
                    box-shadow:0 20px 48px rgba(0,0,0,.10)">

            <div class="inv-center">
                <?php $view->include('invite.partials.ornament', ['name' => 'mandala', 'size' => 'sm']); ?>

                <?php if ($c->has('invocation')): ?>
                    <p class="inv-hero__eyebrow mt-2 mb-2"><?= $c->get('invocation') ?></p>
                <?php endif; ?>

                <?php if ($c->has('groom_name') && $c->has('bride_name')): ?>
                    <div class="inv-names">
                        <span class="inv-names__name"><?= $c->get('groom_name') ?></span>
                        <span class="inv-names__join"><?= e(__('pdf.weds')) ?></span>
                        <span class="inv-names__name"><?= $c->get('bride_name') ?></span>
                    </div>
                <?php else: ?>
                    <h1 class="inv-heading" style="font-size:2.5rem">
                        <?= $c->first('event_name', 'celebrant_name', 'business_name') ?: $c->title() ?>
                    </h1>
                    <?php if ($c->has('years')): ?>
                        <p class="inv-muted mb-0"><?= $c->get('years') ?> years together</p>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="inv-rule"><span class="inv-rule__diamond"></span></div>

                <?php if ($c->eventTimestamp() > 0): ?>
                    <p class="mb-0" style="font-weight:600;font-size:1.05rem">
                        <?= $c->has('wedding_date') ? $c->longDate('wedding_date')
                            : ($c->has('reception_date') ? $c->longDate('reception_date') : $c->longDate('event_date')) ?>
                    </p>
                <?php endif; ?>

                <?php if ($c->has('venue')): ?>
                    <p class="inv-muted mt-1 mb-0"><?= $c->get('venue') ?></p>
                <?php endif; ?>
            </div>
        </div>
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
