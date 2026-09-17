<?php
/**
 * Countdown to the event, in Asia/Kolkata.
 *
 * The target is rendered as a unix timestamp so the browser's clock and
 * timezone cannot shift it.
 *
 * @var App\Services\TemplateContext $c
 */
$timestamp = $c->eventTimestamp();
if ($timestamp === 0) {
    return;
}
?>
<section class="inv-section inv-section--tight inv-reveal" id="countdown">
    <div class="inv-page">
        <p class="inv-section__label"><?= e(__('invite.countdown')) ?></p>

        <div class="inv-countdown" data-inv-countdown="<?= (int) $timestamp ?>"
             role="timer" aria-label="<?= e(__('invite.countdown')) ?>">
            <div class="inv-countdown__cell">
                <div class="inv-countdown__num" data-inv-cd="days">--</div>
                <div class="inv-countdown__label"><?= e(__('invite.days')) ?></div>
            </div>
            <div class="inv-countdown__cell">
                <div class="inv-countdown__num" data-inv-cd="hours">--</div>
                <div class="inv-countdown__label"><?= e(__('invite.hours')) ?></div>
            </div>
            <div class="inv-countdown__cell">
                <div class="inv-countdown__num" data-inv-cd="minutes">--</div>
                <div class="inv-countdown__label"><?= e(__('invite.minutes')) ?></div>
            </div>
            <div class="inv-countdown__cell">
                <div class="inv-countdown__num" data-inv-cd="seconds">--</div>
                <div class="inv-countdown__label"><?= e(__('invite.seconds')) ?></div>
            </div>
        </div>

        <p class="inv-center inv-muted mt-2 mb-0" data-inv-cd-finished <?= $c->isPastEvent() ? '' : 'hidden' ?>>
            <?= e(__('invite.past_event')) ?>
        </p>
    </div>
</section>
