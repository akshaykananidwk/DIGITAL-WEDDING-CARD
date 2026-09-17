<?php
/**
 * Multi-page book.
 *
 * Four pages the guest turns: the invitation, the programme, the venue and
 * the RSVP. Without JavaScript all four pages simply stack, so nothing is
 * ever unreachable.
 *
 * @var App\Services\TemplateContext $c
 */
$events = $c->eventSchedule();
?>
<div class="inv-page" style="padding-top:1.5rem;padding-bottom:1rem">
    <div class="inv-book" data-inv-book>

        <!-- Page 1: the invitation -->
        <section class="inv-book__page is-active">
            <article class="inv-card">
                <div class="inv-frame" style="margin:.9rem">
                    <div class="inv-center">
                        <?php if ($c->has('invocation')): ?>
                            <p class="inv-heading mb-2" style="font-size:1.2rem"><?= $c->get('invocation') ?></p>
                        <?php endif; ?>
                        <?php $view->include('invite.partials.ornament', ['name' => $c->ornament(), 'size' => 'md']); ?>

                        <?php if ($c->has('groom_name') && $c->has('bride_name')): ?>
                            <div class="inv-names mt-3">
                                <span class="inv-names__name"><?= $c->get('groom_name') ?></span>
                                <span class="inv-names__join"><?= e(__('pdf.weds')) ?></span>
                                <span class="inv-names__name"><?= $c->get('bride_name') ?></span>
                            </div>
                        <?php else: ?>
                            <h1 class="inv-heading mt-3" style="font-size:2.3rem"><?= $c->title() ?></h1>
                        <?php endif; ?>

                        <?php if ($c->has('custom_message')): ?>
                            <p class="inv-lead mt-3 mb-0"><?= $c->multiline('custom_message') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        </section>

        <!-- Page 2: the programme -->
        <section class="inv-book__page">
            <article class="inv-card" style="padding:1.4rem 1.2rem">
                <p class="inv-section__label"><?= e(__('invite.programme')) ?></p>
                <?php if ($events === []): ?>
                    <p class="inv-center inv-muted mb-0">
                        <?= $c->eventTimestamp() > 0
                            ? ($c->has('wedding_date') ? $c->longDate('wedding_date') : $c->longDate('event_date'))
                            : '' ?>
                    </p>
                <?php else: ?>
                    <div class="inv-events">
                        <?php foreach ($events as $event): ?>
                            <article class="inv-event">
                                <div class="inv-event__name"><?= $event['label'] ?></div>
                                <div class="inv-event__when">
                                    <?= $event['date'] ?><?= $event['time'] !== '' ? ' · ' . $event['time'] : '' ?>
                                </div>
                                <?php if (($event['venue'] ?? '') !== ''): ?>
                                    <div class="inv-event__when"><?= $event['venue'] ?></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($c->showSection('countdown') && (bool) $c->setting('show_countdown', true)): ?>
                    <div class="mt-4">
                        <?php $view->include('invite.partials.countdown', ['c' => $c]); ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>

        <!-- Page 3: venue and family -->
        <section class="inv-book__page">
            <article class="inv-card" style="padding:1.4rem 1.2rem">
                <?php $view->include('invite.partials.venue', ['c' => $c]); ?>
                <?php $view->include('invite.partials.family', ['c' => $c]); ?>
            </article>
        </section>

        <!-- Page 4: gallery and RSVP -->
        <section class="inv-book__page">
            <article class="inv-card" style="padding:1.4rem 1.2rem">
                <?php $view->include('invite.partials.gallery', ['c' => $c]); ?>
                <?php $view->include('invite.partials.rsvp', ['c' => $c]); ?>
                <?php $view->include('invite.partials.contact', ['c' => $c]); ?>
            </article>
        </section>

        <nav class="inv-book__nav" aria-label="Pages">
            <button type="button" class="inv-btn inv-btn--ghost" data-inv-book-prev>
                <i class="bi" aria-hidden="true">←</i><?= e(__('common.back')) ?>
            </button>
            <div class="inv-book__dots">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <button type="button" class="inv-book__dot <?= $i === 0 ? 'is-active' : '' ?>"
                            aria-label="Page <?= $i + 1 ?>"></button>
                <?php endfor; ?>
            </div>
            <button type="button" class="inv-btn" data-inv-book-next>
                <?= e(__('builder.next')) ?><i class="bi" aria-hidden="true">→</i>
            </button>
        </nav>
    </div>
</div>

<?php $view->include('invite.partials.share', ['c' => $c, 'share' => $share ?? (new App\Services\ShareService())->allLinks($c->invitation())]); ?>
