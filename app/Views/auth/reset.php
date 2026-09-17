<?php
/**
 * @var string $token
 * @var string|null $email
 */
$view->extend('layouts.auth');
?>

<div class="sk-panel">
    <h1 class="h4 mb-1"><?= e(__('auth.reset_title')) ?></h1>
    <p class="text-muted small mb-4">
        Choose a new password for <strong><?= e((string) $email) ?></strong>.
    </p>

    <form method="post" action="<?= e(url('password/reset')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="mb-3">
            <label class="form-label" for="password">New password</label>
            <input class="form-control <?= error_for('password') ? 'is-invalid' : '' ?>" type="password"
                   id="password" name="password" required minlength="8" autofocus autocomplete="new-password">
            <?php if ($message = error_for('password')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
            <div class="form-text">At least 8 characters, with a letter and a number.</div>
        </div>

        <div class="mb-4">
            <label class="form-label" for="password_confirmation">Confirm new password</label>
            <input class="form-control" type="password" id="password_confirmation"
                   name="password_confirmation" required minlength="8" autocomplete="new-password">
        </div>

        <button class="btn btn-primary w-100" type="submit" data-sk-loading="Saving…">Change password</button>
    </form>
</div>
