<?php
/**
 * The RSVP form.
 *
 * Posts normally without JavaScript; invite.js upgrades it to an inline AJAX
 * submission.
 *
 * @var App\Services\TemplateContext $c
 */
if (!$c->showSection('rsvp') || !feature('rsvp', true)) {
    return;
}
$errors = errors();
?>
<section class="inv-section inv-reveal" id="rsvp">
    <div class="inv-page">
        <div class="inv-rsvp">
            <p class="inv-section__label"><?= e($c->sectionTitle('rsvp', __('invite.rsvp_title'))) ?></p>
            <p class="inv-center inv-muted mb-3" style="font-size:.9rem"><?= e(__('invite.rsvp_subtitle')) ?></p>

            <p class="inv-note" id="inv-rsvp-notice" <?= $errors === [] ? 'hidden' : '' ?>>
                <?= $errors === [] ? '' : e((string) (reset($errors)[0] ?? '')) ?>
            </p>

            <form id="inv-rsvp-form" method="post" action="<?= e($c->rsvpUrl()) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="inv-rsvp__choices" role="radiogroup" aria-label="<?= e(__('invite.rsvp_title')) ?>">
                    <label class="inv-rsvp__choice">
                        <input type="radio" name="response" value="yes" checked required>
                        <span><?= e(__('invite.rsvp_yes')) ?></span>
                    </label>
                    <label class="inv-rsvp__choice">
                        <input type="radio" name="response" value="maybe">
                        <span><?= e(__('invite.rsvp_maybe')) ?></span>
                    </label>
                    <label class="inv-rsvp__choice">
                        <input type="radio" name="response" value="no">
                        <span><?= e(__('invite.rsvp_no')) ?></span>
                    </label>
                </div>

                <div class="inv-field">
                    <label for="rsvp-name"><?= e(__('invite.rsvp_name')) ?> *</label>
                    <input type="text" id="rsvp-name" name="name" required maxlength="120"
                           autocomplete="name" value="<?= e(old('name')) ?>">
                </div>

                <div class="inv-field">
                    <label for="rsvp-phone"><?= e(__('invite.rsvp_phone')) ?></label>
                    <input type="tel" id="rsvp-phone" name="phone" maxlength="20"
                           inputmode="tel" autocomplete="tel" value="<?= e(old('phone')) ?>">
                </div>

                <div class="inv-field" data-inv-guests>
                    <label for="rsvp-guests"><?= e(__('invite.rsvp_guests')) ?></label>
                    <input type="number" id="rsvp-guests" name="guests" min="0" max="50" step="1"
                           inputmode="numeric" value="<?= e(old('guests', '1')) ?>">
                </div>

                <div class="inv-field">
                    <label for="rsvp-message"><?= e(__('invite.rsvp_message')) ?></label>
                    <textarea id="rsvp-message" name="message" rows="3" maxlength="1000"><?= e(old('message')) ?></textarea>
                </div>

                <button type="submit" class="inv-btn inv-btn--wide"><?= e(__('invite.rsvp_submit')) ?></button>
            </form>
        </div>
    </div>
</section>
