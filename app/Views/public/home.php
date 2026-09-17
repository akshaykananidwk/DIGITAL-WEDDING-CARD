<?php
/**
 * @var array<int,array<string,mixed>> $categories
 * @var array<int,array<string,mixed>> $featured
 * @var array<int,array<string,mixed>> $latest
 * @var array{total:int,active:int,premium:int,animated:int} $stats
 */
$view->extend('layouts.app');
?>

<section class="sk-hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-12 col-lg-6">
                <p class="sk-hero__eyebrow"><i class="bi bi-stars" aria-hidden="true"></i> <?= e(__('home.eyebrow')) ?></p>
                <h1 class="sk-hero__title"><?= e(__('home.hero_title')) ?></h1>
                <p class="sk-hero__lede"><?= e(__('home.hero_lead')) ?></p>

                <form class="sk-hero__search" method="get" action="<?= e(url('templates')) ?>" role="search">
                    <label class="visually-hidden" for="hero-q"><?= e(__('templates.search')) ?></label>
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input class="form-control" id="hero-q" type="search" name="q" maxlength="80"
                           placeholder="<?= eattr(__('home.search_placeholder')) ?>">
                    <button class="btn btn-primary" type="submit"><?= e(__('common.search')) ?></button>
                </form>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                        <a class="sk-chip" href="<?= e(url('category/' . $category['slug'])) ?>">
                            <?= e($category['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a class="btn btn-primary btn-lg" href="<?= e(url('create')) ?>">
                        <i class="bi bi-magic me-1" aria-hidden="true"></i><?= e(__('home.cta_primary')) ?>
                    </a>
                    <a class="btn btn-outline-secondary btn-lg" href="<?= e(url('templates')) ?>">
                        <?= e(__('home.cta_secondary')) ?>
                    </a>
                </div>

                <ul class="sk-hero__stats list-unstyled">
                    <li><strong><?= number_format($stats['active']) ?>+</strong><span><?= e(__('home.stat_templates')) ?></span></li>
                    <li><strong><?= number_format($stats['animated']) ?></strong><span><?= e(__('home.stat_animated')) ?></span></li>
                    <li><strong>3</strong><span><?= e(__('home.stat_languages')) ?></span></li>
                    <li><strong>₹0</strong><span><?= e(__('home.stat_price')) ?></span></li>
                </ul>
            </div>

            <div class="col-12 col-lg-6">
                <div class="sk-hero__art" aria-hidden="true">
                    <div class="sk-hero__card sk-hero__card--back"></div>
                    <div class="sk-hero__card sk-hero__card--mid"></div>
                    <div class="sk-hero__card sk-hero__card--front">
                        <span class="sk-hero__ganesh">॥ શ્રી ગણેશાય નમઃ ॥</span>
                        <span class="sk-hero__names">રાહુલ <em>&amp;</em> પ્રિયા</span>
                        <span class="sk-hero__date">૨૫ નવેમ્બર ૨૦૨૬</span>
                        <span class="sk-hero__venue">દ્વારકાધીશ મંદિર, દ્વારકા</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container py-5">
    <div class="sk-section-head">
        <h2 class="h3 mb-0"><?= e(__('home.how_title')) ?></h2>
        <p class="text-muted mb-0"><?= e(__('home.how_subtitle')) ?></p>
    </div>
    <div class="row g-4 mt-1">
        <?php
        $steps = [
            ['bi-grid-3x3-gap', __('home.step1_title'), __('home.step1_text')],
            ['bi-pencil-square', __('home.step2_title'), __('home.step2_text')],
            ['bi-share', __('home.step3_title'), __('home.step3_text')],
            ['bi-graph-up-arrow', __('home.step4_title'), __('home.step4_text')],
        ];
        foreach ($steps as $index => [$icon, $title, $text]): ?>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="sk-howto h-100">
                    <span class="sk-howto__num"><?= $index + 1 ?></span>
                    <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                    <h3 class="h6 mt-2 mb-1"><?= e($title) ?></h3>
                    <p class="small text-muted mb-0"><?= e($text) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($featured !== []): ?>
    <section class="container py-4">
        <div class="sk-section-head">
            <div>
                <h2 class="h3 mb-0"><?= e(__('home.featured_title')) ?></h2>
                <p class="text-muted mb-0"><?= e(__('home.featured_subtitle')) ?></p>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('templates', ['sort' => 'featured'])) ?>">
                <?= e(__('common.view_all')) ?>
            </a>
        </div>
        <div class="row g-3 g-md-4 mt-1">
            <?php foreach ($featured as $template): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <?php $view->include('partials.template-card', ['template' => $template]); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="container py-5">
    <div class="sk-section-head">
        <h2 class="h3 mb-0"><?= e(__('home.occasions_title')) ?></h2>
        <p class="text-muted mb-0"><?= e(__('home.occasions_subtitle')) ?></p>
    </div>
    <div class="row g-3 mt-1">
        <?php foreach ($categories as $category): ?>
            <div class="col-12 col-md-6">
                <div class="sk-occasion h-100">
                    <div class="d-flex align-items-start gap-3">
                        <span class="sk-occasion__icon" aria-hidden="true">
                            <i class="bi bi-<?= e((string) ($category['icon'] ?: 'stars')) ?>"></i>
                        </span>
                        <div class="flex-grow-1">
                            <h3 class="h6 mb-1">
                                <a href="<?= e(url('category/' . $category['slug'])) ?>"><?= e($category['name']) ?></a>
                            </h3>
                            <p class="small text-muted mb-2"><?= e((string) $category['description']) ?></p>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach (array_slice($category['subcategories'], 0, 8) as $sub): ?>
                                    <a class="sk-chip sk-chip--sm"
                                       href="<?= e(url('category/' . $category['slug'] . '/' . $sub['slug'])) ?>">
                                        <?= e($sub['name']) ?>
                                    </a>
                                <?php endforeach; ?>
                                <?php if (count($category['subcategories']) > 8): ?>
                                    <a class="sk-chip sk-chip--sm" href="<?= e(url('category/' . $category['slug'])) ?>">
                                        +<?= count($category['subcategories']) - 8 ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="sk-features py-5">
    <div class="container">
        <div class="sk-section-head">
            <h2 class="h3 mb-0"><?= e(__('home.features_title')) ?></h2>
            <p class="text-muted mb-0"><?= e(__('home.features_subtitle')) ?></p>
        </div>
        <div class="row g-4 mt-1">
            <?php
            $features = [
                ['bi-translate', __('home.f_lang_title'), __('home.f_lang_text')],
                ['bi-phone', __('home.f_mobile_title'), __('home.f_mobile_text')],
                ['bi-whatsapp', __('home.f_share_title'), __('home.f_share_text')],
                ['bi-qr-code', __('home.f_qr_title'), __('home.f_qr_text')],
                ['bi-file-earmark-pdf', __('home.f_pdf_title'), __('home.f_pdf_text')],
                ['bi-clipboard-check', __('home.f_rsvp_title'), __('home.f_rsvp_text')],
                ['bi-graph-up', __('home.f_analytics_title'), __('home.f_analytics_text')],
                ['bi-shield-check', __('home.f_privacy_title'), __('home.f_privacy_text')],
            ];
            foreach ($features as [$icon, $title, $text]): ?>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="sk-feature h-100">
                        <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                        <h3 class="h6 mt-2 mb-1"><?= e($title) ?></h3>
                        <p class="small text-muted mb-0"><?= e($text) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($latest !== []): ?>
    <section class="container py-5">
        <div class="sk-section-head">
            <h2 class="h3 mb-0"><?= e(__('home.latest_title')) ?></h2>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('templates', ['sort' => 'latest'])) ?>">
                <?= e(__('common.view_all')) ?>
            </a>
        </div>
        <div class="row g-3 g-md-4 mt-1">
            <?php foreach ($latest as $template): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <?php $view->include('partials.template-card', ['template' => $template]); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php
/*
 * The questions people actually ask before they start. Rendered here because
 * the home page's FAQ structured data describes this very list - Google asks
 * that the answers be on the page, and it is the honest way round.
 */
$faq = App\Services\SeoService::homeFaq();
?>
<?php if ($faq !== []): ?>
    <section class="container pb-5" id="faq">
        <div class="sk-section-head">
            <h2 class="h3 mb-0"><?= e(__('faq.title')) ?></h2>
        </div>
        <div class="accordion mt-3" id="sk-faq">
            <?php foreach ($faq as $i => $pair): ?>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button"
                                data-bs-toggle="collapse" data-bs-target="#faq-<?= (int) $i ?>"
                                aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
                            <?= e($pair['question']) ?>
                        </button>
                    </h3>
                    <div class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>"
                         id="faq-<?= (int) $i ?>" data-bs-parent="#sk-faq">
                        <div class="accordion-body"><?= e($pair['answer']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="container pb-5">
    <div class="sk-cta">
        <h2 class="h3 mb-2"><?= e(__('home.final_cta_title')) ?></h2>
        <p class="mb-3"><?= e(__('home.final_cta_text')) ?></p>
        <a class="btn btn-light btn-lg" href="<?= e(url('create')) ?>">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('home.cta_primary')) ?>
        </a>
    </div>
</section>
