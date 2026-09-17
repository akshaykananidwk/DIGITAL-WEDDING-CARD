<?php
/**
 * The programme: each function with its date, time and venue.
 *
 * @var App\Services\TemplateContext $c
 */
$events = $c->eventSchedule();
if ($events === []) {
    return;
}
?>
<section class="inv-section inv-reveal" id="programme">
    <div class="inv-page">
        <p class="inv-section__label"><?= e($c->sectionTitle('events', __('invite.programme'))) ?></p>

        <div class="inv-events">
            <?php foreach ($events as $event): ?>
                <article class="inv-event">
                    <div class="inv-event__name"><?= $event['label'] ?></div>
                    <?php if ($event['date'] !== '' || $event['time'] !== ''): ?>
                        <div class="inv-event__when">
                            <?php if ($event['date'] !== ''): ?>
                                <i class="bi" aria-hidden="true"></i><?= $event['date'] ?>
                            <?php endif; ?>
                            <?php if ($event['time'] !== ''): ?>
                                <?= $event['date'] !== '' ? ' · ' : '' ?><?= $event['time'] ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (($event['venue'] ?? '') !== ''): ?>
                        <div class="inv-event__when"><?= $event['venue'] ?></div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
