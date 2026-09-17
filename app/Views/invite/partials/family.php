<?php
/**
 * The family names block - the part that matters most in a kankotri.
 *
 * @var App\Services\TemplateContext $c
 */
$names = $c->listOf('family_names');
$hasParents = $c->has('groom_parents') || $c->has('bride_parents');
if ($names === [] && !$hasParents) {
    return;
}
?>
<section class="inv-section inv-reveal" id="family">
    <div class="inv-page inv-center">
        <?php if ($hasParents): ?>
            <div class="row g-3 mb-3" style="display:grid;grid-template-columns:1fr;gap:1rem">
                <?php if ($c->has('groom_parents')): ?>
                    <div>
                        <p class="inv-detail__label mb-1"><?= e(__('pdf.groom_family')) ?></p>
                        <p class="mb-0" style="font-weight:600"><?= $c->multiline('groom_parents') ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($c->has('bride_parents')): ?>
                    <div>
                        <p class="inv-detail__label mb-1"><?= e(__('pdf.bride_family')) ?></p>
                        <p class="mb-0" style="font-weight:600"><?= $c->multiline('bride_parents') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($names !== []): ?>
            <p class="inv-section__label"><?= e($c->sectionTitle('family', __('invite.family'))) ?></p>
            <div class="inv-family">
                <?php foreach ($names as $name): ?>
                    <span class="inv-family__name"><?= e($name) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
