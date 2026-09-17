<?php
/**
 * Admin panel layout.
 *
 * @var App\Core\View $view
 * @var string $content
 */

use App\Core\Auth;
use App\Core\Csp;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Url;

$seo = $seo ?? App\Services\SeoService::make()->noindex();
$user = Auth::user() ?? [];
$path = Request::instance()->path();
$flash = $flash ?? [];

/** Sidebar definition: group => [label, icon, path, permission] */
$nav = [
    'Overview' => [
        ['Dashboard', 'speedometer2', 'admin', null],
        ['Analytics', 'graph-up', 'admin/analytics', 'analytics.view'],
    ],
    'Content' => [
        ['Templates', 'grid-3x3-gap', 'admin/templates', 'templates.view'],
        ['Generate templates', 'magic', 'admin/templates-generate', 'templates.generate'],
        ['Categories', 'diagram-3', 'admin/categories', 'categories.view'],
        ['Invitations', 'envelope-paper', 'admin/invitations', 'invitations.view'],
        ['Media library', 'images', 'admin/media', 'media.view'],
        ['Fonts', 'fonts', 'admin/fonts', 'fonts.view'],
        ['Pages', 'file-text', 'admin/pages', 'pages.view'],
    ],
    'People' => [
        ['Users', 'people', 'admin/users', 'users.view'],
        ['Roles', 'shield-check', 'admin/roles', 'roles.manage'],
    ],
    'Configuration' => [
        ['Settings', 'sliders', 'admin/settings', 'settings.view'],
        ['AI (Gemini)', 'stars', 'admin/ai', 'ai.view'],
        ['Feature flags', 'toggles', 'admin/flags', 'flags.view'],
    ],
    'System' => [
        ['System', 'cpu', 'admin/system', 'health.view'],
        ['Health', 'activity', 'admin/system/health', 'health.view'],
        ['Backups', 'archive', 'admin/system/backups', 'backups.view'],
        ['Updates', 'cloud-arrow-down', 'admin/system/update', 'updates.view'],
        ['Scheduled tasks', 'clock-history', 'admin/system/cron', 'health.view'],
        ['Logs', 'journal-text', 'admin/system/logs', 'logs.view'],
        ['Audit log', 'clipboard-check', 'admin/audit', 'audit.view'],
    ],
];
?>
<!doctype html>
<html lang="<?= e(Lang::htmlLang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?= $seo->render() ?>
    <link rel="icon" href="<?= e(asset('img/favicon-32.png')) ?>" sizes="32x32">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <?= $view->section('head') ?>
</head>
<body>
<div class="sk-admin">
    <div class="sk-admin__overlay" aria-hidden="true"></div>

    <aside class="sk-admin__sidebar" id="sk-admin-sidebar">
        <a class="sk-admin__brand" href="<?= e(url('admin')) ?>">
            <span class="sk-brand__mark" aria-hidden="true">શ</span>
            <span>
                <?= e($appName) ?>
                <small>Admin · v<?= e($appVersion) ?></small>
            </span>
        </a>

        <nav aria-label="Admin">
            <?php foreach ($nav as $group => $items): ?>
                <?php
                $visible = array_values(array_filter(
                    $items,
                    static fn (array $item): bool => $item[3] === null || Auth::can($item[3])
                ));
                if ($visible === []) {
                    continue;
                }
                ?>
                <div class="sk-admin__group"><?= e($group) ?></div>
                <?php foreach ($visible as [$label, $icon, $target, $permission]): ?>
                    <?php
                    $targetPath = '/' . trim($target, '/');
                    $active = $path === $targetPath
                        || ($targetPath !== '/admin' && str_starts_with($path, $targetPath));
                    ?>
                    <a class="sk-admin__link <?= $active ? 'active' : '' ?>" href="<?= e(url($target)) ?>">
                        <i class="bi bi-<?= e($icon) ?>" aria-hidden="true"></i>
                        <span><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <div class="sk-admin__group">Site</div>
            <a class="sk-admin__link" href="<?= e(url('/')) ?>">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span>View site</span>
            </a>
            <a class="sk-admin__link" href="<?= e(url('dashboard')) ?>">
                <i class="bi bi-person-workspace" aria-hidden="true"></i><span>My dashboard</span>
            </a>
        </nav>
    </aside>

    <div class="sk-admin__main">
        <header class="sk-admin__topbar">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button"
                    data-sk-sidebar-toggle aria-expanded="false" aria-controls="sk-admin-sidebar"
                    aria-label="Toggle navigation">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>

            <h1 class="sk-admin-title me-auto"><?= e($pageTitle ?? 'Admin') ?></h1>

            <?php if (($maintenanceActive = (new App\Services\MaintenanceService())->isActive())): ?>
                <span class="badge text-bg-warning"><i class="bi bi-cone-striped me-1"></i>Maintenance</span>
            <?php endif; ?>

            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline ms-1"><?= e(mb_strimwidth((string) ($user['name'] ?? ''), 0, 14, '…')) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-muted"><?= e((string) ($user['role_name'] ?? '')) ?></span></li>
                    <li><a class="dropdown-item" href="<?= e(url('profile')) ?>">My profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="<?= e(url('logout')) ?>" class="px-2">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger w-100" type="submit">Sign out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <div class="sk-admin__content">
            <?= $content ?>
        </div>
    </div>
</div>

<?php foreach ($flash as $message): ?>
    <span hidden data-sk-flash="<?= e($message['type']) ?>"><?= e($message['message']) ?></span>
<?php endforeach; ?>

<script<?= Csp::attribute() ?>>
    window.SK_CONFIG = <?= ejs(['baseUrl' => Url::base(), 'csrfToken' => csrf_token(), 'locale' => $locale]) ?>;
</script>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('js/app.js')) ?>"></script>
<?= $view->section('scripts') ?>
</body>
</html>
