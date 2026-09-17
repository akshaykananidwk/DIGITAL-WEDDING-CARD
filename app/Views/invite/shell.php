<?php
/**
 * The HTML document for a public invitation.
 *
 * Kept separate from the layouts so every template shares the same head,
 * fonts, theme variables and behaviour while looking completely different.
 *
 * @var App\Services\TemplateContext $c
 * @var string $body
 * @var string $styles
 * @var string $scripts
 */

use App\Core\Csp;
use App\Core\Lang;
use App\Core\Url;

$seo = $seo ?? App\Services\SeoService::make()->noindex();
$isPreview = $isPreview ?? false;
$isOwnerView = $isOwnerView ?? false;
$skipAnimation = (bool) $c->setting('skip_animation', false) || $isPreview;
$language = (string) ($c->invitation()['language'] ?? Lang::locale());
$htmlLang = match ($language) {
    'gu' => 'gu-IN',
    'hi' => 'hi-IN',
    default => 'en',
};
?>
<!doctype html>
<html lang="<?= e($htmlLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= e($c->theme('primary', '#C8102E')) ?>">
    <?= $seo->render() ?>

    <link rel="icon" href="<?= e(asset('img/favicon-32.png')) ?>" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">

    <link rel="stylesheet" href="<?= e(asset('css/invite.css')) ?>">
    <style<?= Csp::attribute() ?>>
        <?= $styles ?>
    </style>
</head>
<body>

<?php if ($isOwnerView): ?>
    <div class="inv-draft-banner">
        <i class="bi" aria-hidden="true"></i>
        This invitation is not published yet - only you can see this page.
    </div>
<?php endif; ?>

<div class="inv-root"
     data-motion="<?= e($skipAnimation ? 'none' : $c->motion()) ?>"
     data-inv-share-endpoint="<?= e(url('invite/' . $c->invitation()['slug'] . '/share')) ?>"
     data-inv-token="<?= e(csrf_token()) ?>"
     style="<?= e($c->cssVariables()) ?>">

    <?php if ($c->motion() === 'rich' && !$skipAnimation): ?>
        <div class="inv-petals" aria-hidden="true"></div>
    <?php endif; ?>

    <?php if (!$skipAnimation): ?>
        <?php $view->include('invite.partials.cover', ['c' => $c]); ?>
    <?php endif; ?>

    <?= $body ?>

    <?php if ($c->hasMusic() && $c->showSection('music')): ?>
        <?php $view->include('invite.partials.music', ['c' => $c]); ?>
    <?php endif; ?>

    <?php if ($c->showWatermark()): ?>
        <div class="inv-watermark">
            Created with <a href="<?= e(Url::base()) ?>" rel="noopener"><?= e($appName) ?></a>
        </div>
    <?php endif; ?>
</div>

<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('js/invite.js')) ?>"></script>
<?php if (trim($scripts) !== ''): ?>
    <script<?= Csp::attribute() ?>><?= $scripts ?></script>
<?php endif; ?>
</body>
</html>
