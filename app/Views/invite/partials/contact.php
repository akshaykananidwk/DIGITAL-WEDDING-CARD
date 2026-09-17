<?php
/**
 * RSVP contact details and the WhatsApp chat button.
 *
 * @var App\Services\TemplateContext $c
 */
if (!$c->showSection('contact')) {
    return;
}
$hasContact = $c->has('rsvp_name') || $c->has('rsvp_phone') || $c->has('whatsapp_number');
if (!$hasContact) {
    return;
}
$whatsapp = $c->whatsappContactUrl();
$tel = $c->telLink('rsvp_phone');
?>
<section class="inv-section inv-section--tight inv-reveal" id="contact">
    <div class="inv-page inv-center">
        <p class="inv-section__label"><?= e($c->sectionTitle('contact', __('invite.contact'))) ?></p>

        <?php if ($c->has('rsvp_name')): ?>
            <p class="mb-1" style="font-weight:600"><?= $c->get('rsvp_name') ?></p>
        <?php endif; ?>

        <div class="inv-btn-row">
            <?php if ($tel !== ''): ?>
                <a class="inv-btn inv-btn--ghost" href="<?= e($tel) ?>">
                    <i class="bi bi-telephone" aria-hidden="true"></i><?= $c->get('rsvp_phone') ?>
                </a>
            <?php endif; ?>
            <?php if ($whatsapp !== ''): ?>
                <a class="inv-btn inv-btn--gold" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener"
                   data-inv-share="whatsapp">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i><?= e(__('invite.share_whatsapp')) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
