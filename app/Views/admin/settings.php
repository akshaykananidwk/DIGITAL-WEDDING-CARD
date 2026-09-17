<?php
/**
 * @var array<string,array{title:string,icon:string,fields:array<string,array{0:string,1:string,2:string}>}> $schema
 * @var string $group
 * @var array<string,mixed> $values
 * @var array<string,string> $locales
 * @var string $mailDriver
 */
$view->extend('layouts.admin');
$fields = $schema[$group]['fields'];
$enabledLocales = is_array($values['enabled_locales'] ?? null)
    ? $values['enabled_locales']
    : (is_string($values['enabled_locales'] ?? null) ? (json_decode((string) $values['enabled_locales'], true) ?: []) : []);

/** Controls that need more than a plain text box. */
$choices = [
    'default_locale'  => $locales,
    'mail_driver'     => ['mail' => 'PHP mail()', 'smtp' => 'SMTP', 'log' => 'Write to log (no delivery)'],
    'smtp_encryption' => ['tls' => 'STARTTLS', 'ssl' => 'SSL', 'none' => 'None'],
    'pdf_engine'      => ['auto' => 'Automatic', 'builtin' => 'Built-in writer', 'mpdf' => 'mPDF', 'dompdf' => 'Dompdf'],
    'pdf_paper'       => ['A4' => 'A4', 'A5' => 'A5', 'LETTER' => 'Letter', 'KANKOTRI' => 'Kankotri', 'MOBILE' => 'Mobile'],
];
$hints = [
    'analytics_store_ip'  => 'Off by default. Visitor counts use a salted one-way hash, never a stored IP address.',
    'force_https'         => 'Redirects every request to HTTPS. Turn on once your certificate is live.',
    'csp_enabled'         => 'Sends a Content-Security-Policy with a per-request nonce.',
    'seo_index_invitations' => 'Off keeps individual invitations out of search results.',
    'smtp_password'       => 'Stored encrypted. Leave the dots untouched to keep the current password.',
    'max_image_size'      => 'In bytes. 8388608 is 8 MB.',
    'max_music_size'      => 'In bytes.',
];
?>
<div class="row g-4">
    <div class="col-12 col-lg-3">
        <nav class="sk-panel p-2" aria-label="Settings sections">
            <div class="list-group list-group-flush">
                <?php foreach ($schema as $key => $section): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 <?= $key === $group ? 'active' : '' ?>"
                       href="<?= e(url('admin/settings/' . $key)) ?>">
                        <i class="bi bi-<?= e($section['icon']) ?>" aria-hidden="true"></i>
                        <?= e($section['title']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>
    </div>

    <div class="col-12 col-lg-9">
        <form class="sk-panel" method="post" action="<?= e(url('admin/settings/' . $group)) ?>">
            <?= csrf_field() ?>
            <h2 class="h6 mb-3"><?= e($schema[$group]['title']) ?></h2>

            <div class="row g-3">
                <?php foreach ($fields as $key => [$type, $rules, $label]): ?>
                    <?php
                    $value = $values[$key] ?? '';
                    $invalid = error_for($key);
                    $wide = in_array($type, ['text', 'json'], true);
                    ?>
                    <div class="col-12 <?= $wide ? '' : 'col-sm-6' ?>">
                        <?php if ($type === 'boolean'): ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" value="1" id="<?= e($key) ?>"
                                       name="<?= e($key) ?>" <?= in_array((string) $value, ['1', 'true'], true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= e($key) ?>"><?= e($label) ?></label>
                            </div>

                        <?php elseif ($key === 'enabled_locales'): ?>
                            <span class="form-label d-block"><?= e($label) ?></span>
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($locales as $code => $name): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="loc-<?= e((string) $code) ?>"
                                               name="enabled_locales[]" value="<?= e((string) $code) ?>"
                                            <?= in_array((string) $code, array_map('strval', $enabledLocales), true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="loc-<?= e((string) $code) ?>"><?= e($name) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php elseif (isset($choices[$key])): ?>
                            <label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label>
                            <select class="form-select <?= $invalid ? 'is-invalid' : '' ?>" id="<?= e($key) ?>" name="<?= e($key) ?>">
                                <?php foreach ($choices[$key] as $optionValue => $optionLabel): ?>
                                    <option value="<?= e((string) $optionValue) ?>"
                                        <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>>
                                        <?= e((string) $optionLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($type === 'text'): ?>
                            <label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label>
                            <textarea class="form-control <?= $invalid ? 'is-invalid' : '' ?>" id="<?= e($key) ?>"
                                      name="<?= e($key) ?>" rows="3"><?= e((string) $value) ?></textarea>

                        <?php elseif ($type === 'encrypted'): ?>
                            <label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label>
                            <input class="form-control <?= $invalid ? 'is-invalid' : '' ?>" type="password"
                                   id="<?= e($key) ?>" name="<?= e($key) ?>" autocomplete="new-password"
                                   placeholder="<?= $value === '' ? 'Not set' : '••••••••' ?>">

                        <?php else: ?>
                            <label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label>
                            <input class="form-control <?= $invalid ? 'is-invalid' : '' ?>"
                                   type="<?= $type === 'integer' ? 'number' : 'text' ?>"
                                   id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e((string) $value) ?>">
                        <?php endif; ?>

                        <?php if ($invalid !== null): ?>
                            <div class="invalid-feedback d-block"><?= e($invalid) ?></div>
                        <?php endif; ?>
                        <?php if (isset($hints[$key])): ?>
                            <div class="form-text"><?= e($hints[$key]) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <button class="btn btn-primary" type="submit">Save settings</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/settings/' . $group)) ?>">Reset</a>
            </div>
        </form>

        <?php if ($group === 'email'): ?>
            <section class="sk-panel mt-4">
                <h2 class="h6 mb-2">Send a test email</h2>
                <p class="form-text mt-0">
                    Current driver: <strong><?= e($mailDriver) ?></strong>.
                    With the <code>log</code> driver the message is written to the mail log instead of being sent.
                </p>
                <form class="row g-2 align-items-end" method="post" action="<?= e(url('admin/settings-mail/test')) ?>">
                    <?= csrf_field() ?>
                    <div class="col-12 col-sm-7">
                        <label class="form-label small mb-1" for="test_email">Send to</label>
                        <input class="form-control form-control-sm" type="email" id="test_email" name="to" required>
                    </div>
                    <div class="col-12 col-sm-5">
                        <button class="btn btn-sm btn-outline-primary w-100" type="submit"
                                data-sk-loading="Sending…">Send test</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
