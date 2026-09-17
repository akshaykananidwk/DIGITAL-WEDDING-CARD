<?php
/**
 * Photo gallery, lazy loaded with a WebP source where one exists.
 *
 * @var App\Services\TemplateContext $c
 */
$photos = $c->photos('gallery');
if ($photos === [] || !$c->showSection('gallery')) {
    return;
}
?>
<section class="inv-section inv-reveal" id="gallery">
    <div class="inv-page">
        <p class="inv-section__label"><?= e($c->sectionTitle('gallery', __('invite.gallery'))) ?></p>

        <div class="inv-gallery">
            <?php foreach ($photos as $index => $photo): ?>
                <figure class="inv-gallery__item">
                    <picture>
                        <?php if (!empty($photo['webp_path'])): ?>
                            <source srcset="<?= e(App\Core\Url::upload((string) $photo['webp_path'])) ?>" type="image/webp">
                        <?php endif; ?>
                        <img src="<?= e($c->photoUrl($photo, true)) ?>"
                             alt="<?= e((string) ($photo['caption'] ?? '')) ?>"
                             width="<?= (int) ($photo['width'] ?: 600) ?>"
                             height="<?= (int) ($photo['height'] ?: 600) ?>"
                             loading="<?= $index < 2 ? 'eager' : 'lazy' ?>"
                             decoding="async">
                    </picture>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
