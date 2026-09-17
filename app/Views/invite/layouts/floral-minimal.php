<?php
/**
 * Floral Minimal.
 *
 * Generous whitespace, a single floral flourish, small caps labels.
 *
 * @var App\Services\TemplateContext $c
 */
?>
<section class="inv-hero" style="padding-top:4rem">
    <div class="inv-page">
        <?php $view->include('invite.partials.ornament', ['name' => 'floral', 'size' => 'md']); ?>

        <p class="inv-hero__eyebrow mt-4 mb-3"><?= e(__('invite.save_the_date')) ?></p>

        <?php if ($c->has('groom_name') && $c->has('bride_name')): ?>
            <h1 class="inv-heading" style="font-size:2.9rem;line-height:1.1">
                <?= $c->get('groom_name') ?><br>
                <span style="font-size:.5em;letter-spacing:.2em;text-transform:uppercase;color:var(--inv-secondary)">
                    <?= e(__('pdf.weds')) ?>
                </span><br>
                <?= $c->get('bride_name') ?>
            </h1>
        <?php else: ?>
            <h1 class="inv-heading" style="font-size:2.7rem">
                <?= $c->first('celebrant_name', 'baby_name', 'event_name', 'family_name') ?: $c->title() ?>
            </h1>
            <?php if ($c->has('age')): ?>
                <p class="inv-muted mb-0">turning <?= $c->get('age') ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($c->eventTimestamp() > 0): ?>
            <p class="mt-4 mb-0" style="letter-spacing:.14em;text-transform:uppercase;font-size:.85rem;font-weight:700">
                <?= $c->has('wedding_date') ? $c->date('wedding_date', 'd . m . Y') : $c->date('event_date', 'd . m . Y') ?>
            </p>
        <?php endif; ?>

        <?php if ($c->heroPhotoUrl() !== ''): ?>
            <img class="inv-hero__photo mt-4" src="<?= e($c->heroPhotoUrl()) ?>" alt="" loading="eager" decoding="async"
                 style="border-radius:999px 999px 12px 12px;max-width:20rem;margin:1.5rem auto 0">
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
