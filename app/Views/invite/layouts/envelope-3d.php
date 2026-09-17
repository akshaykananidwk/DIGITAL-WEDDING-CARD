<?php
/**
 * 3D Envelope.
 *
 * The opening animation lives in the cover partial; this layout is the letter
 * that appears once the envelope is open.
 *
 * @var App\Services\TemplateContext $c
 */
?>
<div class="inv-page" style="padding-top:2rem;padding-bottom:1rem">
    <article class="inv-card inv-reveal" style="transform-origin:top center">
        <div style="background:var(--inv-primary);color:#fff;padding:1rem;text-align:center">
            <p class="mb-0" style="letter-spacing:.2em;text-transform:uppercase;font-size:.72rem;opacity:.9">
                <?= e(__('invite.save_the_date')) ?>
            </p>
        </div>

        <div class="inv-frame" style="margin:1rem;border-color:var(--inv-secondary)">
            <div class="inv-center">
                <?php if ($c->has('invocation')): ?>
                    <p class="inv-heading mb-2" style="font-size:1.15rem"><?= $c->get('invocation') ?></p>
                <?php endif; ?>

                <?php if ($c->has('groom_name') && $c->has('bride_name')): ?>
                    <div class="inv-names">
                        <span class="inv-names__name"><?= $c->get('groom_name') ?></span>
                        <span class="inv-names__join"><?= e(__('pdf.weds')) ?></span>
                        <span class="inv-names__name"><?= $c->get('bride_name') ?></span>
                    </div>
                <?php else: ?>
                    <h1 class="inv-heading" style="font-size:2.4rem"><?= $c->first('event_name', 'celebrant_name') ?: $c->title() ?></h1>
                <?php endif; ?>

                <div class="inv-rule"><span class="inv-rule__diamond"></span></div>

                <?php if ($c->has('custom_message')): ?>
                    <p class="inv-lead"><?= $c->multiline('custom_message') ?></p>
                <?php endif; ?>

                <?php if ($c->eventTimestamp() > 0): ?>
                    <div class="inv-detail" style="justify-content:center;border-top:0">
                        <div class="inv-detail__icon"><i class="bi" aria-hidden="true">◈</i></div>
                        <div style="text-align:left">
                            <div class="inv-detail__label"><?= e(__('invite.save_the_date')) ?></div>
                            <div class="inv-detail__value">
                                <?= $c->has('wedding_date') ? $c->longDate('wedding_date') : $c->longDate('event_date') ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </article>
</div>

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
