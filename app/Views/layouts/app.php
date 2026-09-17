<?php
/**
 * Public + signed-in layout.
 *
 * @var App\Core\View $view
 * @var string $content
 */

use App\Core\Auth;
use App\Core\Csp;
use App\Core\Lang;
use App\Core\Session;
use App\Core\Url;

$seo = $seo ?? App\Services\SeoService::make();
$user = Auth::user();
$isSignedIn = $user !== null;
$currentPath = App\Core\Request::instance()->path();
$flash = $flash ?? [];
try {
    $pages       = new App\Repositories\PageRepository();
    $footerPages = $pages->footerLinks();
    $headerPages = $pages->headerLinks();
} catch (Throwable) {
    // Navigation must never take a page down.
    $footerPages = [];
    $headerPages = [];
}
$brandPrimary = (string) (setting('brand_primary') ?: '#C8102E');
?>
<!doctype html>
<html lang="<?= e(Lang::htmlLang()) ?>" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= e($brandPrimary) ?>">
    <?= $seo->render() ?>

    <link rel="icon" href="<?= e(asset('img/favicon-32.png')) ?>" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
    <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">

    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?= $view->section('head') ?>
</head>
<body class="<?= $isSignedIn ? 'has-bottom-nav' : '' ?>">

<a class="visually-hidden-focusable btn btn-primary position-absolute top-0 start-0 m-2" href="#sk-main">
    Skip to content
</a>

<header class="sk-header">
    <div class="container">
        <nav class="d-flex align-items-center gap-2 py-2" aria-label="Main">
            <a class="sk-brand me-auto" href="<?= e(url('/')) ?>">
                <span class="sk-brand__mark" aria-hidden="true">શ</span>
                <span class="d-none d-sm-inline"><?= e($appName) ?></span>
            </a>

            <div class="d-none d-lg-flex align-items-center gap-1">
                <a class="sk-nav-link <?= $currentPath === '/' ? 'active' : '' ?>" href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a>
                <a class="sk-nav-link <?= str_starts_with($currentPath, '/templates') ? 'active' : '' ?>" href="<?= e(url('templates')) ?>"><?= e(__('nav.templates')) ?></a>
                <a class="sk-nav-link <?= str_starts_with($currentPath, '/categor') ? 'active' : '' ?>" href="<?= e(url('categories')) ?>"><?= e(__('nav.categories')) ?></a>
                <?php if ($isSignedIn): ?>
                    <a class="sk-nav-link <?= $currentPath === '/dashboard' ? 'active' : '' ?>" href="<?= e(url('dashboard')) ?>"><?= e(__('nav.dashboard')) ?></a>
                <?php endif; ?>
                <?php foreach ($headerPages as $page): ?>
                    <a class="sk-nav-link <?= $currentPath === '/page/' . $page['slug'] ? 'active' : '' ?>"
                       href="<?= e(url('page/' . $page['slug'])) ?>"><?= e($page['title']) ?></a>
                <?php endforeach; ?>
            </div>

            <!-- Language switcher -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(__('common.language')) ?>">
                    <i class="bi bi-translate" aria-hidden="true"></i>
                    <span class="d-none d-md-inline ms-1"><?= e(strtoupper($locale)) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($locales as $code => $label): ?>
                        <li>
                            <a class="dropdown-item <?= $code === $locale ? 'active' : '' ?>"
                               href="<?= e(url('lang/' . $code)) ?>"><?= e($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($isSignedIn): ?>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        <span class="d-none d-md-inline ms-1"><?= e(mb_strimwidth((string) $user['name'], 0, 14, '…')) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= e(url('dashboard')) ?>"><i class="bi bi-speedometer2 me-2"></i><?= e(__('nav.dashboard')) ?></a></li>
                        <li><a class="dropdown-item" href="<?= e(url('invitations')) ?>"><i class="bi bi-envelope-paper me-2"></i><?= e(__('nav.invitations')) ?></a></li>
                        <li><a class="dropdown-item" href="<?= e(url('analytics')) ?>"><i class="bi bi-graph-up me-2"></i><?= e(__('nav.analytics')) ?></a></li>
                        <li><a class="dropdown-item" href="<?= e(url('profile')) ?>"><i class="bi bi-gear me-2"></i><?= e(__('nav.profile')) ?></a></li>
                        <?php if ($isAdmin): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= e(url('admin')) ?>"><i class="bi bi-shield-lock me-2"></i><?= e(__('nav.admin')) ?></a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= e(url('logout')) ?>" class="px-2">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger w-100" type="submit">
                                    <i class="bi bi-box-arrow-right me-1"></i><?= e(__('nav.logout')) ?>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
                <a class="btn btn-primary btn-sm d-none d-md-inline-flex" href="<?= e(url('create')) ?>">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('nav.create')) ?>
                </a>
            <?php else: ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('login')) ?>"><?= e(__('nav.login')) ?></a>
                <a class="btn btn-sm btn-primary d-none d-sm-inline-flex" href="<?= e(url('register')) ?>"><?= e(__('nav.register')) ?></a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="sk-main">
    <?= $content ?>
</main>

<footer class="sk-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-md-5">
                <div class="sk-brand mb-2" style="color:#FFF3E2">
                    <span class="sk-brand__mark" aria-hidden="true">શ</span>
                    <span><?= e($appName) ?></span>
                </div>
                <p class="small mb-2" style="max-width:26rem"><?= e($appTagline) ?></p>
                <?php if (($note = (string) setting('footer_note', '')) !== ''): ?>
                    <p class="small mb-0"><?= e($note) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-6 col-md-3">
                <div class="fw-semibold mb-2 small text-uppercase" style="letter-spacing:.09em"><?= e(__('nav.templates')) ?></div>
                <ul class="list-unstyled small d-grid gap-1">
                    <li><a href="<?= e(url('templates')) ?>"><?= e(__('templates.title')) ?></a></li>
                    <li><a href="<?= e(url('categories')) ?>"><?= e(__('nav.categories')) ?></a></li>
                    <li><a href="<?= e(url('templates', ['animated' => 1])) ?>"><?= e(__('templates.animated')) ?></a></li>
                    <li><a href="<?= e(url('templates', ['language' => 'gu'])) ?>">ગુજરાતી</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4">
                <div class="fw-semibold mb-2 small text-uppercase" style="letter-spacing:.09em"><?= e(__('nav.help')) ?></div>
                <ul class="list-unstyled small d-grid gap-1">
                    <?php foreach ($footerPages as $page): ?>
                        <li><a href="<?= e(url('page/' . $page['slug'])) ?>"><?= e($page['title']) ?></a></li>
                    <?php endforeach; ?>
                    <?php if (($supportNumber = (string) setting('whatsapp_support_number', '')) !== ''): ?>
                        <li>
                            <a href="https://wa.me/<?= e(App\Core\Str::whatsappNumber($supportNumber)) ?>" rel="noopener">
                                <i class="bi bi-whatsapp me-1"></i>WhatsApp support
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <hr class="sk-footer__divider my-4">
        <div class="d-flex flex-wrap gap-2 justify-content-between small">
            <span>&copy; <?= date('Y') ?> <?= e($appName) ?></span>
            <span class="text-white-50">v<?= e($appVersion) ?></span>
        </div>
    </div>
</footer>

<?php if ($isSignedIn): ?>
    <nav class="sk-bottom-nav" aria-label="Quick navigation">
        <a class="sk-bottom-nav__item <?= $currentPath === '/dashboard' ? 'active' : '' ?>" href="<?= e(url('dashboard')) ?>">
            <i class="bi bi-house-door" aria-hidden="true"></i><span><?= e(__('nav.home')) ?></span>
        </a>
        <a class="sk-bottom-nav__item <?= str_starts_with($currentPath, '/invitations') ? 'active' : '' ?>" href="<?= e(url('invitations')) ?>">
            <i class="bi bi-envelope-paper" aria-hidden="true"></i><span><?= e(__('nav.invitations')) ?></span>
        </a>
        <a class="sk-bottom-nav__item sk-bottom-nav__cta" href="<?= e(url('create')) ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i><span><?= e(__('nav.create')) ?></span>
        </a>
        <a class="sk-bottom-nav__item <?= str_starts_with($currentPath, '/analytics') ? 'active' : '' ?>" href="<?= e(url('analytics')) ?>">
            <i class="bi bi-graph-up" aria-hidden="true"></i><span><?= e(__('nav.analytics')) ?></span>
        </a>
        <a class="sk-bottom-nav__item <?= str_starts_with($currentPath, '/profile') ? 'active' : '' ?>" href="<?= e(url('profile')) ?>">
            <i class="bi bi-person" aria-hidden="true"></i><span><?= e(__('nav.profile')) ?></span>
        </a>
    </nav>
<?php endif; ?>

<?php foreach ($flash as $message): ?>
    <span hidden data-sk-flash="<?= e($message['type']) ?>"><?= e($message['message']) ?></span>
<?php endforeach; ?>

<script<?= Csp::attribute() ?>>
    window.SK_CONFIG = <?= ejs([
        'baseUrl'        => Url::basePath(),
        'csrfToken'      => csrf_token(),
        'locale'         => $locale,
        'serviceWorker'  => Url::path('service-worker.js'),
    ]) ?>;
</script>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('js/app.js')) ?>"></script>
<?= $view->section('scripts') ?>
</body>
</html>
