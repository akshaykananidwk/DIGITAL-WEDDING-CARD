<?php $view->extend('layouts.auth'); ?>

<div class="sk-panel">
    <h1 class="h4 mb-1"><?= e(__('auth.register_title')) ?></h1>
    <p class="text-muted small mb-4"><?= e(__('auth.register_subtitle')) ?></p>

    <form method="post" action="<?= e(url('register')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label class="form-label" for="name"><?= e(__('auth.name')) ?></label>
            <input class="form-control <?= error_for('name') ? 'is-invalid' : '' ?>" type="text" id="name" name="name"
                   required maxlength="120" autocomplete="name" autofocus value="<?= e(old('name')) ?>">
            <?php if ($message = error_for('name')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="email"><?= e(__('auth.email')) ?></label>
            <input class="form-control <?= error_for('email') ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
                   required autocomplete="username" value="<?= e(old('email')) ?>">
            <?php if ($message = error_for('email')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="phone"><?= e(__('auth.phone')) ?> <span class="text-muted">(<?= e(__('common.optional')) ?>)</span></label>
            <input class="form-control <?= error_for('phone') ? 'is-invalid' : '' ?>" type="tel" id="phone" name="phone"
                   inputmode="tel" autocomplete="tel" value="<?= e(old('phone')) ?>">
            <?php if ($message = error_for('phone')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
                <label class="form-label" for="password"><?= e(__('auth.password')) ?></label>
                <input class="form-control <?= error_for('password') ? 'is-invalid' : '' ?>" type="password"
                       id="password" name="password" required minlength="8" autocomplete="new-password">
                <?php if ($message = error_for('password')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label" for="password_confirmation"><?= e(__('auth.password_confirm')) ?></label>
                <input class="form-control" type="password" id="password_confirmation"
                       name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input <?= error_for('terms') ? 'is-invalid' : '' ?>" type="checkbox"
                   id="terms" name="terms" value="1" required>
            <label class="form-check-label small" for="terms">
                I agree to the
                <a href="<?= e(url('page/terms')) ?>" target="_blank">Terms</a> and
                <a href="<?= e(url('page/privacy')) ?>" target="_blank">Privacy Policy</a>.
            </label>
        </div>

        <button class="btn btn-primary w-100 btn-lg" type="submit" data-sk-loading="Creating your account…">
            <?= e(__('nav.register')) ?>
        </button>
    </form>

    <p class="text-center small text-muted mt-4 mb-0">
        <?= e(__('auth.have_account')) ?>
        <a href="<?= e(url('login')) ?>"><?= e(__('nav.login')) ?></a>
    </p>
</div>
