<?php
/**
 * Venue, address and the map/navigation buttons.
 *
 * @var App\Services\TemplateContext $c
 */
if (!$c->has('venue') && !$c->has('venue_address')) {
    return;
}
$mapsUrl = $c->mapsUrl();
?>
<section class="inv-section inv-reveal" id="venue">
    <div class="inv-page inv-center">
        <p class="inv-section__label"><?= e($c->sectionTitle('venue', __('invite.venue'))) ?></p>

        <?php if ($c->has('venue')): ?>
            <h3 class="inv-heading" style="font-size:1.9rem"><?= $c->get('venue') ?></h3>
        <?php endif; ?>

        <?php if ($c->has('venue_address')): ?>
            <p class="inv-muted mt-2 mb-3"><?= $c->multiline('venue_address') ?></p>
        <?php endif; ?>

        <?php if ($mapsUrl !== '' && $c->showSection('map')): ?>
            <div class="inv-btn-row">
                <a class="inv-btn inv-btn--ghost" href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="bi" aria-hidden="true">◎</i><?= e(__('invite.get_directions')) ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
