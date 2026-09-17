<?php
/**
 * The opening screen: an envelope that unseals, or a simple "tap to open".
 *
 * @var App\Services\TemplateContext $c
 */
$is3d = in_array((string) ($c->template()['type'] ?? ''), ['three_d', 'animated'], true)
    || (string) ($c->template()['layout_key'] ?? '') === 'envelope-3d';
$title = $c->first('groom_name') !== '' && $c->first('bride_name') !== ''
    ? $c->get('groom_name') . ' &amp; ' . $c->get('bride_name')
    : $c->title();
?>
<div class="inv-cover" data-inv-skip="<?= $c->setting('skip_animation', false) ? '1' : '0' ?>">
    <button type="button" class="inv-cover__skip" data-inv-skip-button>
        <?= e(__('invite.skip_animation')) ?>
    </button>

    <div>
        <?php if ($is3d): ?>
            <div class="inv-envelope" data-inv-open role="button" tabindex="0"
                 aria-label="<?= e(__('invite.open_invitation')) ?>">
                <div class="inv-envelope__body">
                    <div class="inv-envelope__letter"></div>
                    <div class="inv-envelope__flap"></div>
                    <div class="inv-envelope__seal">શ</div>
                </div>
            </div>
        <?php else: ?>
            <?php $view->include('invite.partials.ornament', ['name' => $c->ornament(), 'size' => 'md']); ?>
        <?php endif; ?>

        <p class="inv-hero__eyebrow mb-1"><?= e(__('invite.save_the_date')) ?></p>
        <h1 class="inv-heading" style="font-size:2.4rem"><?= $title ?></h1>

        <?php if ($c->has('wedding_date') || $c->has('event_date')): ?>
            <p class="inv-muted mt-1 mb-3">
                <?= $c->has('wedding_date') ? $c->longDate('wedding_date') : $c->longDate('event_date') ?>
            </p>
        <?php endif; ?>

        <button type="button" class="inv-btn" data-inv-open>
            <?= e(__('invite.tap_to_open')) ?>
            <i class="bi" aria-hidden="true">→</i>
        </button>
    </div>
</div>
