<?php
/**
 * Centred layout for sign in, registration and password reset.
 *
 * @var App\Core\View $view
 * @var string $content
 */

use App\Core\Csp;
use App\Core\Lang;
use App\Core\Url;

$seo = $seo ?? App\Services\SeoService::make()->noindex();
$flash = $flash ?? [];
?>
<!doctype html>
<html lang="<?= e(Lang::htmlLang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= e((string) (setting('brand_primary') ?: '#C8102E')) ?>">
    <?= $seo->render() ?>
    <link rel="icon" href="<?= e(asset('img/favicon-32.png')) ?>" sizes="32x32">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100">

<div class="container py-4 flex-grow-1 d-flex flex-column">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <a class="sk-brand" href="<?= e(url('/')) ?>">
            <span class="sk-brand__mark" aria-hidden="true">શ</span>
            <span><?= e($appName) ?></span>
        </a>
        <div class="d-flex gap-1">
            <?php foreach ($locales as $code => $label): ?>
                <a class="sk-chip <?= $code === $locale ? 'active' : '' ?> py-1 px-2"
                   style="font-size:.78rem" href="<?= e(url('lang/' . $code)) ?>"><?= e(strtoupper($code)) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row justify-content-center align-items-center flex-grow-1 g-4">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5">
            <?= $content ?>
        </div>

        <div class="col-lg-5 d-none d-lg-block">
            <div class="sk-panel" style="background:linear-gradient(160deg,#FFF8EE,#F6E3C5)">
                <div class="text-center mb-3">
                    <div class="sk-script" style="font-size:2.6rem;color:var(--sk-primary)">શુભ પ્રસંગ</div>
                    <div class="text-muted small"><?= e($appTagline) ?></div>
                </div>
                <ul class="list-unstyled d-grid gap-2 small mb-0">
                    <li><i class="bi bi-check2-circle text-success me-2"></i>Hundreds of ready designs, all free</li>
                    <li><i class="bi bi-check2-circle text-success me-2"></i>Gujarati, Hindi and English</li>
                    <li><i class="bi bi-check2-circle text-success me-2"></i>Share on WhatsApp with one tap</li>
                    <li><i class="bi bi-check2-circle text-success me-2"></i>Print-ready PDF and QR code</li>
                    <li><i class="bi bi-check2-circle text-success me-2"></i>RSVP list and view analytics</li>
                </ul>
            </div>
        </div>
    </div>

    <p class="text-center small text-muted mt-4 mb-0">
        <a href="<?= e(url('page/privacy')) ?>">Privacy</a> ·
        <a href="<?= e(url('page/terms')) ?>">Terms</a> ·
        <a href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a>
    </p>
</div>

<?php foreach ($flash as $message): ?>
    <span hidden data-sk-flash="<?= e($message['type']) ?>"><?= e($message['message']) ?></span>
<?php endforeach; ?>

<script<?= Csp::attribute() ?>>
    window.SK_CONFIG = <?= ejs(['baseUrl' => Url::base(), 'csrfToken' => csrf_token(), 'locale' => $locale]) ?>;
</script>
<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
