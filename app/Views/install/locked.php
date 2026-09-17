<?php
$view->extend('layouts.plain', ['title' => 'Already installed']);
?>
<div class="container py-5" style="max-width:36rem">
    <div class="sk-panel text-center">
        <i class="bi bi-lock-fill text-secondary" style="font-size:2.5rem" aria-hidden="true"></i>
        <h1 class="h4 mt-3 mb-2">Application already installed.</h1>
        <p class="text-muted">
            The installer is locked. To reinstall, remove the configuration file and
            <code>storage/installed.lock</code> from the server first.
        </p>
        <div class="d-flex gap-2 justify-content-center mt-3">
            <a class="btn btn-primary" href="<?= e($loginUrl) ?>">Sign in</a>
            <a class="btn btn-outline-secondary" href="<?= e($siteUrl) ?>">View site</a>
        </div>
    </div>
</div>
