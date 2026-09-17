<?php
/**
 * @var array $steps
 * @var string|null $adminEmail
 */
$view->extend('layouts.plain', ['title' => 'Installation complete']);
?>
<div class="container py-5" style="max-width:44rem">
    <div class="text-center mb-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem" aria-hidden="true"></i>
        <h1 class="h3 mt-3 mb-1">Installation complete</h1>
        <p class="text-muted mb-0">Your invitation platform is ready.</p>
    </div>

    <div class="sk-panel mb-4">
        <div class="sk-panel-title">What was done</div>
        <ul class="list-unstyled mb-0 small">
            <?php foreach ($steps as $step): ?>
                <li class="d-flex gap-2 py-1">
                    <span aria-hidden="true">
                        <i class="bi <?= $step['ok'] ? 'bi-check-circle-fill text-success' : 'bi-exclamation-circle-fill text-warning' ?>"></i>
                    </span>
                    <span>
                        <strong><?= e($step['label']) ?></strong>
                        <span class="text-muted">— <?= e($step['detail']) ?></span>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="sk-panel mb-4">
        <div class="sk-panel-title">Next steps</div>
        <ol class="small mb-0 ps-3">
            <li class="mb-1">Sign in as <strong><?= e((string) $adminEmail) ?></strong>.</li>
            <li class="mb-1">Open <strong>Admin → System → Health</strong> and clear any warnings.</li>
            <li class="mb-1">Set your sender address in <strong>Admin → Settings → Email</strong> so password resets work.</li>
            <li class="mb-1">Add your GitHub repository in <strong>Admin → System → Updates</strong> to enable one-click updates.</li>
            <li class="mb-1">Add the cron command from <strong>Admin → System → Scheduled tasks</strong>.</li>
            <li>Install an SSL certificate, then switch on <strong>Force HTTPS</strong> in Security settings.</li>
        </ol>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-center">
        <a class="btn btn-primary btn-lg" href="<?= e($loginUrl) ?>">Sign in</a>
        <a class="btn btn-outline-secondary btn-lg" href="<?= e($siteUrl) ?>">View site</a>
    </div>

    <p class="text-center small text-muted mt-4 mb-0">
        For extra safety you can now delete nothing at all — the installer has locked itself
        and will refuse to run again.
    </p>
</div>
