<?php
/**
 * The invitation wording.
 *
 * @var App\Services\TemplateContext $c
 */
if (!$c->has('custom_message') && !$c->has('welcome_message')) {
    return;
}
?>
<section class="inv-section inv-reveal" id="blessing">
    <div class="inv-page inv-center">
        <?php if ($c->has('welcome_message')): ?>
            <p class="inv-heading mb-3" style="font-size:1.55rem"><?= $c->get('welcome_message') ?></p>
        <?php endif; ?>

        <?php if ($c->has('custom_message')): ?>
            <p class="inv-lead mb-0"><?= $c->multiline('custom_message') ?></p>
        <?php endif; ?>
    </div>
</section>
