<?php
/**
 * Classic Gujarati Kankotri.
 *
 * A bordered card with the invocation at the top, the couple's names in the
 * heading face, then the details as a traditional column.
 *
 * @var App\Services\TemplateContext $c
 */
?>
<div class="inv-page" style="padding-top:1.5rem;padding-bottom:1.5rem">
    <article class="inv-card">
        <div class="inv-frame" style="margin:.9rem">

            <header class="inv-center">
                <?php if ($c->has('invocation')): ?>
                    <p class="inv-heading" style="font-size:1.25rem;letter-spacing:.06em"><?= $c->get('invocation') ?></p>
                <?php endif; ?>

                <?php $view->include('invite.partials.ornament', ['name' => $c->ornament(), 'size' => 'md']); ?>

                <?php if ($c->has('groom_name') && $c->has('bride_name')): ?>
                    <div class="inv-names mt-3">
                        <span class="inv-names__name"><?= $c->get('groom_name') ?></span>
                        <span class="inv-names__join"><?= e(__('pdf.weds')) ?></span>
                        <span class="inv-names__name"><?= $c->get('bride_name') ?></span>
                    </div>
                <?php else: ?>
                    <h1 class="inv-heading mt-2" style="font-size:2.4rem"><?= $c->first('event_name', 'business_name', 'celebrant_name', 'family_name') ?: $c->title() ?></h1>
                <?php endif; ?>

                <?php if ($c->has('wedding_date') || $c->has('event_date') || $c->has('opening_date')): ?>
                    <?php $view->include('invite.partials.divider', ['c' => $c]); ?>
                    <p class="mb-0" style="font-weight:600;font-size:1.05rem">
                        <?= $c->has('wedding_date') ? $c->longDate('wedding_date')
                            : ($c->has('event_date') ? $c->longDate('event_date') : $c->longDate('opening_date')) ?>
                    </p>
                    <?php if ($c->has('wedding_time') || $c->has('event_time')): ?>
                        <p class="inv-muted mb-0">
                            <?= $c->has('wedding_time') ? $c->time('wedding_time') : $c->time('event_time') ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </header>

            <?php if ($c->has('custom_message')): ?>
                <div class="inv-center mt-4">
                    <p class="inv-lead mb-0"><?= $c->multiline('custom_message') ?></p>
                </div>
            <?php endif; ?>

            <?php if ($c->has('groom_parents') || $c->has('bride_parents')): ?>
                <div class="inv-center mt-4" style="display:grid;gap:1rem">
                    <?php if ($c->has('groom_parents')): ?>
                        <div>
                            <p class="inv-detail__label mb-1"><?= e(__('pdf.groom_family')) ?></p>
                            <p class="mb-0" style="font-weight:600"><?= $c->multiline('groom_parents') ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($c->has('bride_parents')): ?>
                        <div>
                            <p class="inv-detail__label mb-1"><?= e(__('pdf.bride_family')) ?></p>
                            <p class="mb-0" style="font-weight:600"><?= $c->multiline('bride_parents') ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="inv-center mt-4">
                <?php $view->include('invite.partials.ornament', ['name' => 'line', 'size' => 'lg']); ?>
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
