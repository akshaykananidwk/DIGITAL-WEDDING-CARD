<?php $view->extend('layouts.auth'); ?>

<div class="sk-panel">
    <h1 class="h4 mb-1"><?= e(__('auth.login_title')) ?></h1>
    <p class="text-muted small mb-4"><?= e(__('auth.login_subtitle')) ?></p>

    <form method="post" action="<?= e(url('login')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label class="form-label" for="email"><?= e(__('auth.email')) ?></label>
            <input class="form-control <?= error_for('email') ? 'is-invalid' : '' ?>"
                   type="email" id="email" name="email" required autocomplete="username"
                   autofocus value="<?= e(old('email')) ?>">
            <?php if ($message = error_for('email')): ?>
                <div class="invalid-feedback"><?= e($message) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0" for="password"><?= e(__('auth.password')) ?></label>
                <a class="small" href="<?= e(url('password/forgot')) ?>"><?= e(__('auth.forgot')) ?></a>
            </div>
            <input class="form-control mt-1 <?= error_for('password') ? 'is-invalid' : '' ?>"
                   type="password" id="password" name="password" required autocomplete="current-password">
            <?php if ($message = error_for('password')): ?>
                <div class="invalid-feedback"><?= e($message) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
            <label class="form-check-label small" for="remember"><?= e(__('auth.remember')) ?></label>
        </div>

        <button class="btn btn-primary w-100 btn-lg" type="submit" data-sk-loading="Signing in…">
            <?= e(__('nav.login')) ?>
        </button>
    </form>

    <?php if (setting('registration_open', true) && feature('registration', true)): ?>
        <p class="text-center small text-muted mt-4 mb-0">
            <?= e(__('auth.no_account')) ?>
            <a href="<?= e(url('register')) ?>"><?= e(__('nav.register')) ?></a>
        </p>
    <?php endif; ?>
</div>
