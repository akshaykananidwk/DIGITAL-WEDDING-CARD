<?php
/**
 * Minimal layout for the installer and error pages: no database, no session
 * assumptions, no navigation.
 *
 * @var App\Core\View $view
 * @var string $content
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Shubh Kankotri') ?></title>
    <link rel="icon" href="<?= e(asset('img/favicon-32.png')) ?>" sizes="32x32">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?= $view->section('head') ?>
</head>
<body class="bg-body-tertiary">
<?= $content ?>
<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>" defer></script>
<?= $view->section('scripts') ?>
</body>
</html>
