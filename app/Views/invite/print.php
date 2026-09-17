<?php
/**
 * Print / PDF markup for the optional HTML PDF engines (mPDF, Dompdf).
 *
 * Deliberately plain: no JavaScript, no external requests, inline CSS only,
 * and a single column that fits A4 without reflow. The built-in PDF writer
 * does not use this file - it composes the page itself - so this stays as
 * simple as the HTML engines need it to be.
 *
 * @var App\Services\TemplateContext $c
 * @var App\Services\TemplateEngine $engine
 */

$invitation = $c->invitation();
$primary = $c->theme('primary', '#C8102E');
$secondary = $c->theme('secondary', '#F0B429');
$text = $c->theme('text', '#2E2118');
$background = $c->theme('background', '#FFF8EE');

$fontStack = match ((string) ($invitation['language'] ?? 'en')) {
    'gu' => "'Noto Sans Gujarati', 'DejaVu Sans', sans-serif",
    'hi' => "'Noto Sans Devanagari', 'DejaVu Sans', sans-serif",
    default => "'DejaVu Sans', sans-serif",
};

$events = $c->eventSchedule();
$hero = $c->heroPhotoUrl();
?>
<!doctype html>
<html lang="<?= e((string) ($invitation['language'] ?? 'en')) ?>">
<head>
    <meta charset="utf-8">
    <title><?= e($c->title()) ?></title>
    <style>
        @page { margin: 12mm 10mm; }
        body {
            font-family: <?= $fontStack ?>;
            color: <?= e($text) ?>;
            background: <?= e($background) ?>;
            font-size: 11pt;
            line-height: 1.55;
            margin: 0;
        }
        .card {
            border: 2px solid <?= e($primary) ?>;
            padding: 10mm 8mm;
            text-align: center;
        }
        .rule { height: 2px; background: <?= e($secondary) ?>; width: 40%; margin: 4mm auto; }
        .invocation { color: <?= e($primary) ?>; font-size: 11pt; letter-spacing: .04em; }
        .names { font-size: 24pt; line-height: 1.2; color: <?= e($primary) ?>; margin: 3mm 0; }
        .joiner { font-size: 13pt; color: <?= e($secondary) ?>; }
        .label { font-size: 8.5pt; letter-spacing: .12em; text-transform: uppercase; color: <?= e($secondary) ?>; }
        .when { font-size: 13pt; font-weight: bold; margin: 2mm 0; }
        .block { margin-top: 6mm; }
        .events { width: 100%; border-collapse: collapse; margin-top: 3mm; }
        .events td { padding: 2mm 1mm; border-bottom: 1px solid <?= e($secondary) ?>; font-size: 10pt; text-align: left; }
        .events td.name { font-weight: bold; white-space: nowrap; }
        .hero { width: 60mm; height: 60mm; object-fit: cover; }
        .muted { color: #6B5B45; font-size: 9.5pt; }
        .qr { width: 28mm; height: 28mm; }
        .footer { margin-top: 8mm; font-size: 8.5pt; color: #6B5B45; }
    </style>
</head>
<body>
<div class="card">
    <?php if (($invocation = $c->first('invocation', 'blessing')) !== ''): ?>
        <p class="invocation"><?= $invocation ?></p>
    <?php endif; ?>

    <?php if ($hero !== ''): ?>
        <p><img class="hero" src="<?= e($hero) ?>" alt=""></p>
    <?php endif; ?>

    <div class="rule"></div>

    <?php if (($groom = $c->first('groom_name', 'celebrant_name', 'business_name', 'child_name')) !== ''): ?>
        <p class="names">
            <?= $groom ?>
            <?php if (($bride = $c->get('bride_name')) !== ''): ?>
                <br><span class="joiner">&amp;</span><br><?= $bride ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (($message = $c->first('invitation_message', 'message', 'blessing')) !== ''): ?>
        <p><?= nl2br($message) ?></p>
    <?php endif; ?>

    <div class="rule"></div>

    <?php if (($date = $c->longDate('event_date')) !== '' || $c->eventIso() !== ''): ?>
        <div class="block">
            <p class="label"><?= e(__('invite.save_the_date')) ?></p>
            <p class="when">
                <?= $date !== '' ? $date : e(date('l, j F Y', $c->eventTimestamp())) ?>
                <?php if (($time = $c->first('wedding_time', 'event_time', 'opening_time')) !== ''): ?>
                    <br><?= $time ?>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($events !== []): ?>
        <div class="block">
            <p class="label"><?= e(__('invite.programme')) ?></p>
            <table class="events">
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td class="name"><?= $event['label'] ?></td>
                        <td>
                            <?= $event['date'] ?><?= $event['time'] !== '' ? ' · ' . $event['time'] : '' ?>
                            <?php if (($event['venue'] ?? '') !== ''): ?><br><span class="muted"><?= $event['venue'] ?></span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <?php if (($venue = $c->first('venue_name', 'venue', 'location_name')) !== ''): ?>
        <div class="block">
            <p class="label"><?= e(__('invite.venue')) ?></p>
            <p><strong><?= $venue ?></strong>
                <?php if (($address = $c->multiline('venue_address')) !== ''): ?>
                    <br><span class="muted"><?= $address ?></span>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php foreach (['groom_parents' => 'family', 'bride_parents' => 'family', 'family_names' => 'family'] as $key => $_): ?>
        <?php if ($c->has($key)): ?>
            <p class="muted"><?= $c->multiline($key) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (($contact = $c->first('contact_name', 'contact_person')) !== '' || $c->has('contact_phone')): ?>
        <div class="block">
            <p class="label"><?= e(__('invite.contact')) ?></p>
            <p class="muted"><?= $contact ?> <?= $c->get('contact_phone') ?></p>
        </div>
    <?php endif; ?>

    <div class="footer">
        <?= e($c->publicUrl()) ?>
    </div>
</div>
</body>
</html>
