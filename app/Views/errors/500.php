<?php
/**
 * Production error page.
 *
 * Shows a reference id the user can quote; the detail is in the log, never on
 * screen unless debug mode is explicitly on.
 *
 * @var Throwable $exception
 * @var string $errorReference
 * @var bool $showDebug
 */
$errorReference = $errorReference ?? '';
$showDebug = $showDebug ?? false;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e(__('errors.500_title')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-4">
    <div style="max-width:44rem;width:100%">
        <div class="text-center">
            <div class="sk-script" style="font-size:4rem;color:var(--sk-primary);line-height:1">Oops</div>
            <h1 class="h3 mb-2"><?= e(__('errors.500_title')) ?></h1>
            <p class="text-muted"><?= e(__('errors.500_body')) ?></p>
            <?php if ($errorReference !== ''): ?>
                <p class="small text-muted">
                    <?= e(__('errors.reference')) ?>: <code><?= e($errorReference) ?></code>
                </p>
            <?php endif; ?>
            <a class="btn btn-primary mt-2" href="<?= e(url('/')) ?>"><?= e(__('errors.go_home')) ?></a>
        </div>

        <?php if ($showDebug && isset($exception)): ?>
            <div class="sk-code mt-4">
<?= e($exception::class . ': ' . $exception->getMessage()) ?>

<?= e($exception->getFile() . ':' . $exception->getLine()) ?>

<?= e($exception->getTraceAsString()) ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
