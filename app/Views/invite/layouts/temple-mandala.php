<?php
/**
 * Temple Mandala.
 *
 * Built for pooja, katha and mandir functions: the deity and host come first,
 * then the ritual timings.
 *
 * @var App\Services\TemplateContext $c
 */
?>
<section class="inv-hero" style="padding-top:2.5rem">
    <div class="inv-page">
        <?php $view->include('invite.partials.ornament', ['name' => 'temple', 'size' => 'md']); ?>

        <?php if ($c->has('invocation')): ?>
            <p class="inv-heading mt-3 mb-1" style="font-size:1.3rem"><?= $c->get('invocation') ?></p>
        <?php endif; ?>

        <h1 class="inv-heading mt-2" style="font-size:2.5rem">
            <?= $c->first('event_name', 'deity_name', 'family_name', 'celebrant_name') ?: $c->title() ?>
        </h1>

        <?php if ($c->has('deity_name') && $c->has('event_name')): ?>
            <p class="inv-muted mt-1 mb-0"><?= $c->get('deity_name') ?></p>
        <?php endif; ?>

        <div class="inv-rule"><span class="inv-rule__diamond"></span></div>

        <?php if ($c->has('host_name') || $c->has('family_name')): ?>
            <p class="mb-0">
                <span class="inv-detail__label d-block"><?= e(__('pdf.with_best_wishes')) ?></span>
                <strong><?= $c->first('host_name', 'family_name') ?></strong>
            </p>
        <?php endif; ?>

        <?php if ($c->has('priest_name')): ?>
            <p class="inv-muted mt-2 mb-0" style="font-size:.9rem">
                <?= $c->get('priest_name') ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<?php $view->include('invite.partials.blessing', ['c' => $c]); ?>

<section class="inv-section inv-reveal">
    <div class="inv-page">
        <?php if ($c->eventTimestamp() > 0): ?>
            <div class="inv-detail">
                <div class="inv-detail__icon"><i class="bi" aria-hidden="true">◈</i></div>
                <div>
                    <div class="inv-detail__label"><?= e(__('invite.save_the_date')) ?></div>
                    <div class="inv-detail__value"><?= $c->longDate('event_date') ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($c->has('event_time')): ?>
            <div class="inv-detail">
                <div class="inv-detail__icon"><i class="bi" aria-hidden="true">◷</i></div>
                <div>
                    <div class="inv-detail__label">Time</div>
                    <div class="inv-detail__value"><?= $c->time('event_time') ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($c->has('pooja_time')): ?>
            <div class="inv-detail">
                <div class="inv-detail__icon"><i class="bi" aria-hidden="true">◷</i></div>
                <div>
                    <div class="inv-detail__label">Vastu pooja</div>
                    <div class="inv-detail__value"><?= $c->time('pooja_time') ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($c->has('prasad_time')): ?>
            <div class="inv-detail">
                <div class="inv-detail__icon"><i class="bi" aria-hidden="true">✿</i></div>
                <div>
                    <div class="inv-detail__label">Prasad</div>
                    <div class="inv-detail__value"><?= $c->time('prasad_time') ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

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
