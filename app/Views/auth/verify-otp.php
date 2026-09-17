<?php
/**
 * @var string $email masked
 */
$view->extend('layouts.auth');
?>
<div class="sk-panel">
    <h1 class="h4 mb-1"><?= e(__('auth.otp_title')) ?></h1>
    <p class="text-muted small mb-4">
        <?= e(__('auth.otp_subtitle', ['email' => $email])) ?>
    </p>

    <form method="post" action="<?= e(url('login/verify')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="code"><?= e(__('auth.otp_code')) ?></label>
            <input class="form-control form-control-lg text-center <?= error_for('code') ? 'is-invalid' : '' ?>"
                   type="text" id="code" name="code" required autofocus autocomplete="one-time-code"
                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   style="letter-spacing:.5em; font-variant-numeric: tabular-nums">
            <?php if ($message = error_for('code')): ?>
                <div class="invalid-feedback"><?= e($message) ?></div>
            <?php endif; ?>
            <div class="form-text"><?= e(__('auth.otp_hint')) ?></div>
        </div>

        <button class="btn btn-primary w-100 btn-lg" type="submit" data-sk-loading="<?= eattr(__('common.loading')) ?>">
            <?= e(__('auth.otp_submit')) ?>
        </button>
    </form>

    <form class="mt-3" method="post" action="<?= e(url('login/verify/resend')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-link w-100 p-0" type="submit"><?= e(__('auth.otp_resend')) ?></button>
    </form>

    <p class="text-center small text-muted mt-4 mb-0">
        <a href="<?= e(url('login')) ?>"><?= e(__('auth.otp_start_over')) ?></a>
    </p>
</div>
