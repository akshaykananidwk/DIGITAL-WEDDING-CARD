<?php
/**
 * Step 8: share.
 *
 * @var array<string,mixed> $invitation
 * @var array<string,mixed> $share
 * @var int $step
 */
$view->extend('layouts.app');
$id = (int) $invitation['id'];
$isPublished = (string) $invitation['status'] === 'published';
?>
<div class="container py-4">
    <?php $view->include('partials.wizard-steps', ['step' => 8, 'invitation' => $invitation]); ?>

    <?php if ($isPublished): ?>
        <div class="sk-published mt-4">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <div>
                <h1 class="h5 mb-1"><?= e(__('builder.published')) ?></h1>
                <p class="small mb-0"><?= e(__('builder.share_hint')) ?></p>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mt-4 small">
            <?= e(__('builder.not_published')) ?>
            <a href="<?= e(url('builder/' . $id, ['step' => 7])) ?>"><?= e(__('builder.publish')) ?></a>
        </div>
    <?php endif; ?>

    <div class="row g-4 mt-2">
        <div class="col-12 col-lg-7">
            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3"><?= e(__('builder.your_link')) ?></h2>

                <label class="form-label small mb-1" for="share-url"><?= e(__('builder.full_link')) ?></label>
                <div class="input-group mb-3">
                    <input class="form-control" type="text" id="share-url" readonly
                           value="<?= e((string) $share['public_url']) ?>">
                    <button class="btn btn-outline-secondary" type="button" data-sk-copy="#share-url"
                            data-sk-copy-message="<?= eattr(__('invite.link_copied')) ?>">
                        <i class="bi bi-clipboard me-1" aria-hidden="true"></i><?= e(__('common.copy')) ?>
                    </button>
                </div>

                <label class="form-label small mb-1" for="short-url"><?= e(__('builder.short_link')) ?></label>
                <div class="input-group mb-3">
                    <input class="form-control" type="text" id="short-url" readonly
                           value="<?= e((string) $share['short_url']) ?>">
                    <button class="btn btn-outline-secondary" type="button" data-sk-copy="#short-url"
                            data-sk-copy-message="<?= eattr(__('invite.link_copied')) ?>">
                        <i class="bi bi-clipboard me-1" aria-hidden="true"></i><?= e(__('common.copy')) ?>
                    </button>
                </div>

                <label class="form-label small mb-1" for="share-message"><?= e(__('builder.whatsapp_message')) ?></label>
                <textarea class="form-control mb-2" id="share-message" rows="5" readonly><?= e((string) $share['message']) ?></textarea>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-sk-copy="#share-message"
                        data-sk-copy-message="<?= eattr(__('invite.link_copied')) ?>">
                    <i class="bi bi-clipboard me-1" aria-hidden="true"></i><?= e(__('common.copy')) ?>
                </button>
            </section>

            <section class="sk-panel">
                <h2 class="h6 mb-3"><?= e(__('invite.share')) ?></h2>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-success" href="<?= e((string) $share['whatsapp']) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-whatsapp me-1" aria-hidden="true"></i><?= e(__('invite.share_whatsapp')) ?>
                    </a>
                    <a class="btn btn-outline-secondary" href="<?= e((string) $share['facebook']) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-facebook me-1" aria-hidden="true"></i>Facebook
                    </a>
                    <a class="btn btn-outline-secondary" href="<?= e((string) $share['telegram']) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-telegram me-1" aria-hidden="true"></i>Telegram
                    </a>
                    <a class="btn btn-outline-secondary" href="<?= e((string) $share['x']) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-twitter-x me-1" aria-hidden="true"></i>X
                    </a>
                    <a class="btn btn-outline-secondary" href="<?= e((string) $share['email']) ?>">
                        <i class="bi bi-envelope me-1" aria-hidden="true"></i>Email
                    </a>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-5">
            <section class="sk-panel mb-4 text-center">
                <h2 class="h6 mb-3"><?= e(__('invite.scan_qr')) ?></h2>
                <img class="sk-qr" src="<?= e((string) $share['qr_png']) ?>" alt="<?= eattr(__('invite.scan_qr')) ?>"
                     width="240" height="240" loading="lazy">
                <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e((string) $share['qr_png'] . '?download=1') ?>">
                        PNG
                    </a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e((string) $share['qr_png'] . '?print=1&download=1') ?>">
                        <?= e(__('builder.qr_print')) ?>
                    </a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e((string) $share['qr_svg']) ?>">
                        SVG
                    </a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e((string) $share['pdf']) ?>">
                        <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>PDF
                    </a>
                </div>

                <hr>

                <p class="form-text mt-0"><?= e(__('builder.qr_extras_hint')) ?></p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a class="btn btn-sm btn-outline-secondary"
                       href="<?= e(url('invite/' . $invitation['slug'] . '/qr-venue.png', ['download' => 1])) ?>">
                        <i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= e(__('builder.qr_venue')) ?>
                    </a>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="<?= e(url('invite/' . $invitation['slug'] . '/qr-rsvp.png', ['download' => 1])) ?>">
                        <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i><?= e(__('builder.qr_rsvp')) ?>
                    </a>
                </div>
            </section>

            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3"><?= e(__('rsvp.title')) ?></h2>
                <dl class="row small mb-3">
                    <dt class="col-7 text-muted"><?= e(__('rsvp.attending')) ?></dt>
                    <dd class="col-5 text-end mb-1"><?= number_format((int) $share['rsvp_count']['yes']) ?></dd>
                    <dt class="col-7 text-muted"><?= e(__('rsvp.guests')) ?></dt>
                    <dd class="col-5 text-end mb-0"><?= number_format((int) $share['rsvp_count']['guests']) ?></dd>
                </dl>
                <div class="d-grid gap-2">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('invitations/' . $id . '/rsvp')) ?>">
                        <?= e(__('rsvp.title')) ?>
                    </a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('invitations/' . $id . '/analytics')) ?>">
                        <?= e(__('analytics.title')) ?>
                    </a>
                </div>
            </section>

            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="<?= e(url('builder/' . $id, ['step' => 3])) ?>">
                    <i class="bi bi-pencil me-1" aria-hidden="true"></i><?= e(__('common.edit')) ?>
                </a>
                <a class="btn btn-primary" href="<?= e((string) $share['public_url']) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i><?= e(__('common.view')) ?>
                </a>
            </div>
        </div>
    </div>
</div>
