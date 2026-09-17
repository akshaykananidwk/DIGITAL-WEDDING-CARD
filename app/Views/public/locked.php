<?php
/**
 * Shown when a public invitation is password protected.
 *
 * @var array<string,mixed> $invitation
 */
$view->extend('layouts.auth');
?>
<div class="sk-panel text-center">
    <i class="bi bi-lock-fill display-5 text-primary" aria-hidden="true"></i>
    <h1 class="h5 mt-3 mb-1"><?= e(__('invite.locked_title')) ?></h1>
    <p class="text-muted small mb-4"><?= e(__('invite.locked_text')) ?></p>

    <form method="post" action="<?= e(url('invite/' . $invitation['slug'] . '/unlock')) ?>" class="text-start">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="password"><?= e(__('invite.password')) ?></label>
            <input class="form-control <?= error_for('password') ? 'is-invalid' : '' ?>" type="password"
                   id="password" name="password" required autofocus autocomplete="off">
            <?php if ($message = error_for('password')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
        </div>
        <button class="btn btn-primary w-100" type="submit"><?= e(__('invite.unlock')) ?></button>
    </form>
</div>
