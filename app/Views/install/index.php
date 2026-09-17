<?php
/**
 * The installer.
 *
 * One page, three sections, no page reloads between them - a fresh install
 * should take about a minute.
 *
 * @var array $requirements
 * @var array $defaults
 * @var string $csrf
 */
$view->extend('layouts.plain', ['title' => 'Install · Shubh Kankotri']);
$error = $error ?? null;
$steps = $steps ?? [];
$d = $defaults;
?>
<div class="container py-4 py-md-5" style="max-width:56rem">

    <header class="text-center mb-4">
        <div class="sk-brand justify-content-center mb-2" style="font-size:1.5rem">
            <span class="sk-brand__mark" aria-hidden="true">શ</span>
            <span>Shubh Kankotri</span>
        </div>
        <p class="text-muted mb-0">
            Installer · v<?= e($appVersion) ?> · PHP <?= e($phpVersion) ?>
        </p>
    </header>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger">
            <strong>Installation could not complete.</strong>
            <div><?= e($error) ?></div>
            <?php if ($steps !== []): ?>
                <ul class="small mb-0 mt-2">
                    <?php foreach ($steps as $step): ?>
                        <li>
                            <?= $step['ok'] ? '✓' : '✕' ?> <?= e($step['label']) ?> — <?= e($step['detail']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ---------------- Step 1: requirements ---------------- -->
    <section class="sk-panel mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h5 mb-0">1 · System requirements</h2>
            <span class="badge <?= $requirements['ok'] ? 'text-bg-success' : 'text-bg-danger' ?>">
                <?= $requirements['ok'] ? 'Ready to install' : 'Action needed' ?>
            </span>
        </div>

        <?php foreach ($requirements['groups'] as $group => $items): ?>
            <?php if ($items === []) { continue; } ?>
            <div class="sk-panel-title mt-3"><?= e($group) ?></div>
            <ul class="list-unstyled mb-0 small">
                <?php foreach ($items as $item): ?>
                    <li class="d-flex gap-2 py-1">
                        <span aria-hidden="true">
                            <?php if ($item['passed']): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                            <?php elseif ($item['required']): ?>
                                <i class="bi bi-x-circle-fill text-danger"></i>
                            <?php else: ?>
                                <i class="bi bi-exclamation-circle-fill text-warning"></i>
                            <?php endif; ?>
                        </span>
                        <span>
                            <strong><?= e($item['label']) ?></strong>
                            <span class="text-muted">— <?= e($item['detail']) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>

        <?php if (!$requirements['ok']): ?>
            <div class="alert alert-warning mt-3 mb-0 small">
                Fix the items marked in red, then reload this page. The warnings in amber are optional
                but some features will be limited without them.
            </div>
        <?php endif; ?>
    </section>

    <form method="post" action="<?= e(url('install')) ?>" id="install-form" novalidate>
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">

        <!-- ---------------- Step 2: database ---------------- -->
        <section class="sk-panel mb-4">
            <h2 class="h5 mb-3">2 · Database</h2>
            <p class="text-muted small">
                Create a MySQL/MariaDB database in your hosting panel, then enter its details here.
                On most shared hosts the host name is <code>localhost</code>.
            </p>

            <div class="row g-3">
                <div class="col-8 col-md-6">
                    <label class="form-label" for="db_host">Host</label>
                    <input class="form-control" id="db_host" name="db_host" value="<?= e((string) $d['db_host']) ?>" required>
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label" for="db_port">Port</label>
                    <input class="form-control" id="db_port" name="db_port" type="number" value="<?= e((string) $d['db_port']) ?>" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="db_prefix">Table prefix</label>
                    <input class="form-control" id="db_prefix" name="db_prefix" value="<?= e((string) $d['db_prefix']) ?>"
                           placeholder="optional">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="db_database">Database name</label>
                    <input class="form-control" id="db_database" name="db_database" value="<?= e((string) $d['db_database']) ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="db_username">Database user</label>
                    <input class="form-control" id="db_username" name="db_username" value="<?= e((string) $d['db_username']) ?>"
                           autocomplete="off" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="db_password">Database password</label>
                    <input class="form-control" id="db_password" name="db_password" type="password" autocomplete="new-password">
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 mt-3">
                <button class="btn btn-outline-primary" type="button" id="test-db">Test connection</button>
                <span id="db-result" class="small"></span>
            </div>
        </section>

        <!-- ---------------- Step 3: site & administrator ---------------- -->
        <section class="sk-panel mb-4">
            <h2 class="h5 mb-3">3 · Site &amp; administrator</h2>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="site_name">Site name</label>
                    <input class="form-control" id="site_name" name="site_name" value="<?= e((string) $d['site_name']) ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="site_url">Site URL</label>
                    <input class="form-control" id="site_url" name="site_url" value="<?= e((string) $d['site_url']) ?>">
                    <div class="form-text">Detected automatically. Change it if you use a different domain.</div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="locale">Default language</label>
                    <select class="form-select" id="locale" name="locale">
                        <option value="en">English</option>
                        <option value="gu">ગુજરાતી</option>
                        <option value="hi">हिन्दी</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="template_count">Starter templates</label>
                    <select class="form-select" id="template_count" name="template_count">
                        <option value="0">Curated only (51)</option>
                        <option value="250" selected>Curated + 250 variants</option>
                        <option value="1000">Curated + 1,000 variants</option>
                        <option value="2500">Curated + 2,500 variants</option>
                    </select>
                    <div class="form-text">Variants combine layouts, palettes and fonts.</div>
                </div>
                <div class="col-12 col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="create_demo" name="create_demo" value="1" checked>
                        <label class="form-check-label" for="create_demo">Create a demo invitation</label>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="admin_name">Your name</label>
                    <input class="form-control" id="admin_name" name="admin_name"
                           value="<?= e((string) ($d['admin_name'] ?? '')) ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="admin_email">Your email</label>
                    <input class="form-control" id="admin_email" name="admin_email" type="email"
                           value="<?= e((string) ($d['admin_email'] ?? '')) ?>" required autocomplete="username">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="admin_password">Password</label>
                    <input class="form-control" id="admin_password" name="admin_password" type="password"
                           required minlength="8" autocomplete="new-password">
                    <div class="form-text">At least 8 characters, with a letter and a number.</div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="admin_password_confirmation">Confirm password</label>
                    <input class="form-control" id="admin_password_confirmation" name="admin_password_confirmation"
                           type="password" required minlength="8" autocomplete="new-password">
                </div>
                <div class="col-12">
                    <label class="form-label" for="admin_phone">Mobile number <span class="text-muted">(optional)</span></label>
                    <input class="form-control" id="admin_phone" name="admin_phone" type="tel">
                </div>
            </div>
        </section>

        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button class="btn btn-primary btn-lg" type="submit" <?= $requirements['ok'] ? '' : 'disabled' ?>
                    data-sk-loading="Installing…">
                Install now
            </button>
            <span class="small text-muted">
                This creates the tables, seeds the template catalogue and writes the configuration.
            </span>
        </div>
    </form>

    <p class="text-center small text-muted mt-4 mb-0">
        The installer locks itself once finished. Nothing is written until you press Install.
    </p>
</div>

<?php $view->start('scripts'); ?>
<script>
(function () {
    var button = document.getElementById('test-db');
    var result = document.getElementById('db-result');
    if (!button) { return; }

    button.addEventListener('click', async function () {
        var form = document.getElementById('install-form');
        var body = new FormData();
        ['_token', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password', 'db_prefix'].forEach(function (name) {
            var field = form.elements[name];
            if (field) { body.append(name, field.value); }
        });

        button.disabled = true;
        result.className = 'small text-muted';
        result.textContent = 'Testing…';

        try {
            var response = await fetch('<?= e(url('install/database')) ?>', {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            var payload = await response.json();
            result.className = 'small ' + (payload.success ? 'text-success' : 'text-danger');
            result.textContent = payload.message || (payload.success ? 'Connected.' : 'Could not connect.');
        } catch (error) {
            result.className = 'small text-danger';
            result.textContent = 'The test request failed.';
        }
        button.disabled = false;
    });
})();
</script>
<?php $view->stop(); ?>
