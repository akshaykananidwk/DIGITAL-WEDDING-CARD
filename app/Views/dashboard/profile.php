<?php
/**
 * @var array<string,mixed> $user
 * @var array{count:int,bytes:int} $storage
 * @var array<string,mixed> $limits
 * @var array<int,array<string,mixed>> $tokens
 * @var array<string,string> $locales
 */
$view->extend('layouts.app');
$avatar = (string) ($user['avatar'] ?? '');
$mb = static fn (int $bytes): string => number_format($bytes / 1048576, 1) . ' MB';
?>
<div class="container py-4">
    <h1 class="h4 mb-4"><?= e(__('nav.profile')) ?></h1>

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3"><?= e(__('profile.details')) ?></h2>

                <div class="d-flex align-items-center gap-3 mb-3">
                    <?php if ($avatar !== ''): ?>
                        <img class="sk-avatar" src="<?= e(url($avatar)) ?>" alt="" width="64" height="64">
                    <?php else: ?>
                        <span class="sk-avatar sk-avatar--initial" aria-hidden="true">
                            <?= e(mb_substr((string) $user['name'], 0, 1)) ?>
                        </span>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('profile/avatar')) ?>" enctype="multipart/form-data"
                          class="d-flex flex-wrap align-items-center gap-2">
                        <?= csrf_field() ?>
                        <input class="form-control form-control-sm <?= error_for('avatar') ? 'is-invalid' : '' ?>"
                               type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
                               aria-label="<?= eattr(__('profile.avatar')) ?>" required>
                        <button class="btn btn-sm btn-outline-secondary" type="submit"><?= e(__('common.save')) ?></button>
                        <?php if ($message = error_for('avatar')): ?>
                            <div class="invalid-feedback d-block w-100"><?= e($message) ?></div>
                        <?php endif; ?>
                    </form>
                </div>

                <form method="post" action="<?= e(url('profile')) ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="name"><?= e(__('auth.name')) ?></label>
                            <input class="form-control <?= error_for('name') ? 'is-invalid' : '' ?>" type="text"
                                   id="name" name="name" required maxlength="120"
                                   value="<?= e(old('name', (string) $user['name'])) ?>">
                            <?php if ($message = error_for('name')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="email"><?= e(__('auth.email')) ?></label>
                            <input class="form-control <?= error_for('email') ? 'is-invalid' : '' ?>" type="email"
                                   id="email" name="email" required value="<?= e(old('email', (string) $user['email'])) ?>">
                            <?php if ($message = error_for('email')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="phone"><?= e(__('auth.phone')) ?></label>
                            <input class="form-control <?= error_for('phone') ? 'is-invalid' : '' ?>" type="tel"
                                   id="phone" name="phone" value="<?= e(old('phone', (string) ($user['phone'] ?? ''))) ?>">
                            <?php if ($message = error_for('phone')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="city"><?= e(__('profile.city')) ?></label>
                            <input class="form-control" type="text" id="city" name="city" maxlength="80"
                                   value="<?= e(old('city', (string) ($user['city'] ?? ''))) ?>">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="locale"><?= e(__('common.language')) ?></label>
                            <select class="form-select" id="locale" name="locale">
                                <?php foreach ($locales as $code => $label): ?>
                                    <option value="<?= e((string) $code) ?>"
                                        <?= (string) ($user['locale'] ?? '') === (string) $code ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3" type="submit"><?= e(__('common.save')) ?></button>
                </form>
            </section>

            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3"><?= e(__('profile.password')) ?></h2>
                <form method="post" action="<?= e(url('profile/password')) ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="current_password"><?= e(__('profile.current_password')) ?></label>
                            <input class="form-control <?= error_for('current_password') ? 'is-invalid' : '' ?>"
                                   type="password" id="current_password" name="current_password" required
                                   autocomplete="current-password">
                            <?php if ($message = error_for('current_password')): ?>
                                <div class="invalid-feedback"><?= e($message) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="new_password"><?= e(__('profile.new_password')) ?></label>
                            <input class="form-control <?= error_for('password') ? 'is-invalid' : '' ?>" type="password"
                                   id="new_password" name="password" required minlength="8" autocomplete="new-password">
                            <?php if ($message = error_for('password')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="password_confirmation"><?= e(__('auth.password_confirm')) ?></label>
                            <input class="form-control" type="password" id="password_confirmation"
                                   name="password_confirmation" required minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                    <button class="btn btn-outline-primary mt-3" type="submit"><?= e(__('profile.change_password')) ?></button>
                </form>
            </section>

            <section class="sk-panel border-danger-subtle">
                <h2 class="h6 text-danger mb-2"><?= e(__('profile.delete_account')) ?></h2>
                <p class="small text-muted"><?= e(__('profile.delete_warning')) ?></p>
                <form method="post" action="<?= e(url('profile/delete')) ?>"
                      data-sk-confirm-form="<?= eattr(__('profile.delete_confirm')) ?>">
                    <?= csrf_field() ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-5">
                            <label class="form-label small" for="delete_password"><?= e(__('auth.password')) ?></label>
                            <input class="form-control form-control-sm <?= error_for('password') ? 'is-invalid' : '' ?>"
                                   type="password" id="delete_password" name="password" required autocomplete="off">
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label small" for="confirm"><?= e(__('profile.type_delete')) ?></label>
                            <input class="form-control form-control-sm <?= error_for('confirm') ? 'is-invalid' : '' ?>"
                                   type="text" id="confirm" name="confirm" required placeholder="DELETE" autocomplete="off">
                            <?php if ($message = error_for('confirm')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12 col-sm-3">
                            <button class="btn btn-sm btn-danger w-100" type="submit"><?= e(__('common.delete')) ?></button>
                        </div>
                    </div>
                </form>
            </section>
        </div>

        <div class="col-12 col-lg-5">
            <section class="sk-panel mb-4">
                <h2 class="h6 mb-3"><?= e(__('profile.account')) ?></h2>
                <dl class="row small mb-0">
                    <dt class="col-6 text-muted"><?= e(__('profile.member_since')) ?></dt>
                    <dd class="col-6"><?= e(date('d M Y', strtotime((string) $user['created_at']))) ?></dd>
                    <dt class="col-6 text-muted"><?= e(__('profile.email_verified')) ?></dt>
                    <dd class="col-6">
                        <?php if (($user['email_verified_at'] ?? null) !== null): ?>
                            <span class="badge text-bg-success"><?= e(__('common.yes')) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><?= e(__('common.no')) ?></span>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-6 text-muted"><?= e(__('profile.storage')) ?></dt>
                    <dd class="col-6"><?= e($mb($storage['bytes'])) ?> · <?= number_format($storage['count']) ?></dd>
                </dl>

                <?php if ($limits !== []): ?>
                    <hr>
                    <h3 class="h6 small text-uppercase text-muted"><?= e(__('profile.plan_limits')) ?></h3>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($limits as $key => $value): ?>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted"><?= e(ucwords(str_replace('_', ' ', (string) $key))) ?></span>
                                <span><?= e(is_bool($value) ? ($value ? __('common.yes') : __('common.no')) : (string) $value) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="sk-panel mb-4">
                <h2 class="h6 mb-2"><?= e(__('profile.your_data')) ?></h2>
                <p class="small text-muted"><?= e(__('profile.export_hint')) ?></p>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('profile/export')) ?>">
                    <i class="bi bi-download me-1" aria-hidden="true"></i><?= e(__('profile.export')) ?>
                </a>
            </section>

            <?php if ($tokens !== []): ?>
                <section class="sk-panel">
                    <h2 class="h6 mb-3"><?= e(__('profile.api_tokens')) ?></h2>
                    <ul class="list-unstyled small mb-0 d-grid gap-2">
                        <?php foreach ($tokens as $token): ?>
                            <li class="d-flex justify-content-between gap-2">
                                <span><?= e($token['name']) ?></span>
                                <span class="text-muted">
                                    <?= $token['last_used_at'] === null
                                        ? e(__('common.none'))
                                        : e(date('d M Y', strtotime((string) $token['last_used_at']))) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
