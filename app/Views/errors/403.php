<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e(__('errors.403_title')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-4">
    <div class="text-center" style="max-width:32rem">
        <div class="sk-script" style="font-size:5rem;color:var(--sk-primary);line-height:1">403</div>
        <h1 class="h3 mb-2"><?= e(__('errors.403_title')) ?></h1>
        <p class="text-muted"><?= e(__('errors.403_body')) ?></p>
        <a class="btn btn-primary mt-3" href="<?= e(url('/')) ?>"><?= e(__('errors.go_home')) ?></a>
    </div>
</body>
</html>
