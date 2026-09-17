<?php
/**
 * Share row, calendar buttons, PDF and QR.
 *
 * @var App\Services\TemplateContext $c
 */
if (!$c->showSection('share')) {
    return;
}
$share = $share ?? [];
$publicUrl = $c->publicUrl();
$shortUrl = $c->shortUrl();
?>
<section class="inv-section inv-reveal" id="share">
    <div class="inv-page inv-center">
        <p class="inv-section__label"><?= e(__('invite.share')) ?></p>

        <div class="inv-share mb-3">
            <?php if (!empty($share['whatsapp'])): ?>
                <a class="inv-share__btn" href="<?= e($share['whatsapp']) ?>" target="_blank" rel="noopener"
                   data-inv-share="whatsapp" aria-label="WhatsApp" title="WhatsApp">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
            <?php if (!empty($share['facebook'])): ?>
                <a class="inv-share__btn" href="<?= e($share['facebook']) ?>" target="_blank" rel="noopener"
                   data-inv-share="facebook" aria-label="Facebook" title="Facebook">
                    <i class="bi bi-facebook" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
            <?php if (!empty($share['telegram'])): ?>
                <a class="inv-share__btn" href="<?= e($share['telegram']) ?>" target="_blank" rel="noopener"
                   data-inv-share="telegram" aria-label="Telegram" title="Telegram">
                    <i class="bi bi-telegram" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
            <?php if (!empty($share['x'])): ?>
                <a class="inv-share__btn" href="<?= e($share['x']) ?>" target="_blank" rel="noopener"
                   data-inv-share="x" aria-label="X" title="X">
                    <i class="bi bi-twitter-x" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
            <?php if (!empty($share['email'])): ?>
                <a class="inv-share__btn" href="<?= e($share['email']) ?>"
                   data-inv-share="email" aria-label="Email" title="Email">
                    <i class="bi bi-envelope" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
            <button type="button" class="inv-share__btn" data-inv-copy="<?= e($shortUrl) ?>"
                    data-inv-copied="<i class='bi bi-check2'></i>"
                    aria-label="<?= e(__('invite.copy_link')) ?>" title="<?= e(__('invite.copy_link')) ?>">
                <i class="bi bi-link-45deg" aria-hidden="true"></i>
            </button>
            <button type="button" class="inv-share__btn" data-inv-native-share
                    data-title="<?= e(strip_tags($c->title())) ?>"
                    data-url="<?= e($publicUrl) ?>"
                    aria-label="Share" title="Share" hidden>
                <i class="bi bi-share" aria-hidden="true"></i>
            </button>
        </div>

        <div class="inv-btn-row">
            <?php if ($c->eventTimestamp() > 0): ?>
                <a class="inv-btn inv-btn--ghost" href="<?= e($c->googleCalendarUrl()) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-calendar-plus" aria-hidden="true"></i><?= e(__('invite.google_calendar')) ?>
                </a>
                <a class="inv-btn inv-btn--ghost" href="<?= e($c->icsUrl()) ?>">
                    <i class="bi bi-calendar-event" aria-hidden="true"></i><?= e(__('invite.download_ics')) ?>
                </a>
            <?php endif; ?>
            <?php if (feature('pdf_export', true)): ?>
                <a class="inv-btn inv-btn--ghost" href="<?= e($c->pdfUrl()) ?>">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i><?= e(__('invite.download_pdf')) ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($c->showSection('qr', false) || (bool) $c->setting('show_qr', false)): ?>
            <div class="mt-4">
                <div class="sk-qr-box">
                    <img src="<?= e($c->qrUrl()) ?>" alt="<?= e(__('invite.scan_qr')) ?>" width="220" height="220" loading="lazy">
                </div>
                <p class="inv-muted mt-2 mb-0" style="font-size:.8rem"><?= e(__('invite.scan_qr')) ?></p>
            </div>
        <?php endif; ?>

        <p class="inv-muted mt-3 mb-0" style="font-size:.78rem; word-break:break-all"><?= e($shortUrl) ?></p>
    </div>
</section>
