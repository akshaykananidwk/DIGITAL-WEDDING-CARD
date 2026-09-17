<?php $view->extend('layouts.auth'); ?>

<div class="sk-panel">
    <h1 class="h4 mb-1"><?= e(__('auth.reset_title')) ?></h1>
    <p class="text-muted small mb-4"><?= e(__('auth.reset_subtitle')) ?></p>

    <form method="post" action="<?= e(url('password/forgot')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="email"><?= e(__('auth.email')) ?></label>
            <input class="form-control <?= error_for('email') ? 'is-invalid' : '' ?>" type="email" id="email"
                   name="email" required autofocus autocomplete="username" value="<?= e(old('email')) ?>">
            <?php if ($message = error_for('email')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
        </div>
        <button class="btn btn-primary w-100" type="submit" data-sk-loading="Sending…">Send reset link</button>
    </form>

    <p class="text-center small text-muted mt-4 mb-0">
        <a href="<?= e(url('login')) ?>">Back to sign in</a>
    </p>
</div>
