<?php
/**
 * Route table.
 *
 * Middleware is declared per route (or per group) rather than globally, so
 * nothing is exempt from CSRF or authentication by accident.
 */

declare(strict_types=1);

use App\Controllers\Admin;
use App\Controllers\Api;
use App\Controllers\Install\InstallController;
use App\Controllers\Web;
use App\Core\Application;

/** @var Application $this */
$router = Application::getInstance()->router();

// ---------------------------------------------------------------------------
//  Installer - the only routes that work before installation
// ---------------------------------------------------------------------------
$router->get('/install', [InstallController::class, 'index'], [], 'install');
$router->post('/install/requirements', [InstallController::class, 'requirements'], ['throttle:install,30,60']);
$router->post('/install/database', [InstallController::class, 'testDatabase'], ['throttle:install,30,60']);
$router->post('/install', [InstallController::class, 'install'], ['throttle:install,10,600']);

// ---------------------------------------------------------------------------
//  Public site
// ---------------------------------------------------------------------------
$router->group('', ['installed', 'maintenance'], static function ($router): void {
    $router->get('/', [Web\HomeController::class, 'index'], [], 'home');

    $router->get('/templates', [Web\TemplateBrowseController::class, 'index'], [], 'templates');
    $router->get('/templates/{slug:[a-z0-9\-]+}', [Web\TemplateBrowseController::class, 'show'], [], 'template.show');
    $router->get('/templates/{slug:[a-z0-9\-]+}/preview', [Web\TemplateBrowseController::class, 'preview'], [], 'template.preview');

    $router->get('/categories', [Web\CategoryController::class, 'index'], [], 'categories');
    $router->get('/category/{slug:[a-z0-9\-]+}', [Web\CategoryController::class, 'show'], [], 'category.show');
    $router->get(
        '/category/{slug:[a-z0-9\-]+}/{sub:[a-z0-9\-]+}',
        [Web\CategoryController::class, 'showSubcategory'],
        [],
        'subcategory.show'
    );

    $router->get('/page/{slug:[a-z0-9\-]+}', [Web\PageController::class, 'show'], [], 'page.show');
    $router->get('/lang/{locale:[a-z]{2}}', [Web\LocaleController::class, 'switch'], [], 'locale.switch');

    // SEO and PWA
    $router->get('/sitemap.xml', [Web\SeoController::class, 'sitemap']);
    $router->get('/robots.txt', [Web\SeoController::class, 'robots']);
    $router->get('/manifest.webmanifest', [Web\SeoController::class, 'manifest']);
    $router->get('/service-worker.js', [Web\SeoController::class, 'serviceWorker']);
    $router->get('/offline', [Web\SeoController::class, 'offline'], [], 'offline');

    // ---- Public invitation ----
    $router->get('/i/{code:[A-Za-z0-9]{4,12}}', [Web\PublicInvitationController::class, 'short'], [], 'invite.short');
    $router->get('/invite/{slug:[A-Za-z0-9\-]+}', [Web\PublicInvitationController::class, 'show'], [], 'invite.show');
    $router->post(
        '/invite/{slug:[A-Za-z0-9\-]+}/unlock',
        [Web\PublicInvitationController::class, 'unlock'],
        ['csrf', 'throttle:unlock,20,600']
    );
    $router->get('/invite/{slug:[A-Za-z0-9\-]+}/pdf', [Web\InvitationExportController::class, 'pdf'], ['throttle:pdf,30,600'], 'invite.pdf');
    $router->get('/invite/{slug:[A-Za-z0-9\-]+}/qr.png', [Web\InvitationExportController::class, 'qrPng'], [], 'invite.qr.png');
    $router->get('/invite/{slug:[A-Za-z0-9\-]+}/qr.svg', [Web\InvitationExportController::class, 'qrSvg'], [], 'invite.qr.svg');
    // Separate codes for the venue signboard and the RSVP desk.
    $router->get('/invite/{slug:[A-Za-z0-9\-]+}/qr-venue.png', [Web\InvitationExportController::class, 'qrVenue'], [], 'invite.qr.venue');
    $router->get('/invite/{slug:[A-Za-z0-9\-]+}/qr-rsvp.png', [Web\InvitationExportController::class, 'qrRsvp'], [], 'invite.qr.rsvp');
    $router->get('/invite/{slug:[A-Za-z0-9\-]+}/calendar.ics', [Web\InvitationExportController::class, 'ics'], [], 'invite.ics');
    $router->post(
        '/invite/{slug:[A-Za-z0-9\-]+}/rsvp',
        [Web\RsvpController::class, 'store'],
        ['csrf', 'throttle:rsvp-post,20,3600'],
        'invite.rsvp'
    );
    $router->post(
        '/invite/{slug:[A-Za-z0-9\-]+}/share',
        [Web\PublicInvitationController::class, 'recordShare'],
        ['csrf', 'throttle:share,60,600']
    );
});

// ---------------------------------------------------------------------------
//  Authentication
// ---------------------------------------------------------------------------
$router->group('', ['installed', 'maintenance', 'guest'], static function ($router): void {
    $router->get('/login', [Web\AuthController::class, 'showLogin'], [], 'login');
    $router->post('/login', [Web\AuthController::class, 'login'], ['csrf', 'throttle:login,20,600']);
    // Second factor: reachable only with a pending login in the session.
    $router->get('/login/verify', [Web\AuthController::class, 'showOtp'], [], 'login.verify');
    $router->post('/login/verify', [Web\AuthController::class, 'verifyOtp'], ['csrf', 'throttle:otp,30,600']);
    $router->post('/login/verify/resend', [Web\AuthController::class, 'resendOtp'], ['csrf', 'throttle:otp-send,6,3600']);

    $router->get('/register', [Web\AuthController::class, 'showRegister'], [], 'register');
    $router->post('/register', [Web\AuthController::class, 'register'], ['csrf', 'throttle:register,10,3600']);

    $router->get('/password/forgot', [Web\PasswordController::class, 'showForgot'], [], 'password.forgot');
    $router->post('/password/forgot', [Web\PasswordController::class, 'sendLink'], ['csrf', 'throttle:password,6,3600']);
    $router->get('/password/reset/{token:[A-Za-z0-9\.]+}', [Web\PasswordController::class, 'showReset'], [], 'password.reset');
    $router->post('/password/reset', [Web\PasswordController::class, 'reset'], ['csrf', 'throttle:password,10,3600']);
});

$router->group('', ['installed'], static function ($router): void {
    $router->post('/logout', [Web\AuthController::class, 'logout'], ['csrf'], 'logout');
    $router->get('/verify/{token:[A-Za-z0-9]+}', [Web\AuthController::class, 'verifyEmail'], [], 'verify');
});

// ---------------------------------------------------------------------------
//  Signed-in user area
// ---------------------------------------------------------------------------
$router->group('', ['installed', 'maintenance', 'auth'], static function ($router): void {
    $router->get('/dashboard', [Web\DashboardController::class, 'index'], [], 'dashboard');

    $router->get('/invitations', [Web\InvitationController::class, 'index'], [], 'invitations');
    $router->get('/invitations/{id:\d+}/rsvp', [Web\RsvpController::class, 'manage'], [], 'invitations.rsvp');
    $router->get('/invitations/{id:\d+}/rsvp/export', [Web\RsvpController::class, 'export']);
    $router->post('/invitations/{id:\d+}/rsvp/read', [Web\RsvpController::class, 'markRead'], ['csrf']);
    $router->delete('/invitations/{id:\d+}/rsvp/{rsvpId:\d+}', [Web\RsvpController::class, 'destroy'], ['csrf']);
    $router->get('/invitations/{id:\d+}/analytics', [Web\AnalyticsController::class, 'show'], [], 'invitations.analytics');
    $router->get('/invitations/{id:\d+}/analytics/export', [Web\AnalyticsController::class, 'export']);

    $router->get('/analytics', [Web\AnalyticsController::class, 'index'], [], 'analytics');

    // ---- Builder ----
    $router->get('/create', [Web\BuilderController::class, 'start'], [], 'create');
    $router->get('/create/templates', [Web\BuilderController::class, 'chooseTemplate'], [], 'create.templates');
    $router->post('/create/suggest', [Web\BuilderController::class, 'suggest'], ['csrf', 'throttle:suggest,30,600']);
    $router->post('/create/start', [Web\BuilderController::class, 'create'], ['csrf', 'throttle:create,30,3600']);

    $router->get('/builder/{id:\d+}', [Web\BuilderController::class, 'edit'], [], 'builder');
    $router->post('/builder/{id:\d+}/content', [Web\BuilderController::class, 'saveContent'], ['csrf']);
    $router->post('/builder/{id:\d+}/design', [Web\BuilderController::class, 'saveDesign'], ['csrf']);
    $router->post('/builder/{id:\d+}/photos', [Web\BuilderController::class, 'uploadPhotos'], ['csrf', 'throttle:upload,60,600']);
    $router->post('/builder/{id:\d+}/photos/reorder', [Web\BuilderController::class, 'reorderPhotos'], ['csrf']);
    $router->delete('/builder/{id:\d+}/photos/{photoId:\d+}', [Web\BuilderController::class, 'deletePhoto'], ['csrf']);
    $router->post('/builder/{id:\d+}/music', [Web\BuilderController::class, 'saveMusic'], ['csrf', 'throttle:upload,30,600']);
    $router->delete('/builder/{id:\d+}/music', [Web\BuilderController::class, 'deleteMusic'], ['csrf']);
    $router->post('/builder/{id:\d+}/publish', [Web\BuilderController::class, 'publish'], ['csrf']);
    $router->post('/builder/{id:\d+}/unpublish', [Web\BuilderController::class, 'unpublish'], ['csrf']);
    $router->post('/builder/{id:\d+}/slug', [Web\BuilderController::class, 'changeSlug'], ['csrf']);
    $router->post('/builder/{id:\d+}/short-code', [Web\BuilderController::class, 'newShortCode'], ['csrf']);
    $router->post('/builder/{id:\d+}/duplicate', [Web\BuilderController::class, 'duplicate'], ['csrf']);
    $router->delete('/builder/{id:\d+}', [Web\BuilderController::class, 'destroy'], ['csrf']);
    $router->get('/builder/{id:\d+}/preview', [Web\BuilderController::class, 'preview'], [], 'builder.preview');
    // The builder posts unsaved values in so the live preview comes from the same engine.
    $router->post('/builder/{id:\d+}/preview', [Web\BuilderController::class, 'preview'], ['csrf']);
    $router->get('/builder/{id:\d+}/share', [Web\BuilderController::class, 'share'], [], 'builder.share');

    // ---- Profile ----
    $router->get('/profile', [Web\ProfileController::class, 'edit'], [], 'profile');
    $router->post('/profile', [Web\ProfileController::class, 'update'], ['csrf']);
    $router->post('/profile/password', [Web\ProfileController::class, 'changePassword'], ['csrf']);
    $router->post('/profile/avatar', [Web\ProfileController::class, 'uploadAvatar'], ['csrf', 'throttle:upload,20,600']);
    $router->post('/profile/delete', [Web\ProfileController::class, 'destroy'], ['csrf']);
    $router->get('/profile/export', [Web\ProfileController::class, 'exportData']);

    $router->get('/notifications', [Web\NotificationController::class, 'index'], [], 'notifications');
    $router->post('/notifications/{id:\d+}/read', [Web\NotificationController::class, 'markRead'], ['csrf']);
    $router->post('/notifications/read-all', [Web\NotificationController::class, 'markAllRead'], ['csrf']);
});

// ---------------------------------------------------------------------------
//  Admin panel
// ---------------------------------------------------------------------------
$router->group('admin', ['installed', 'admin'], static function ($router): void {
    $router->get('', [Admin\DashboardController::class, 'index'], [], 'admin');

    // Users and roles
    $router->get('/users', [Admin\UserController::class, 'index'], ['can:users.view'], 'admin.users');
    $router->get('/users/create', [Admin\UserController::class, 'create'], ['can:users.create']);
    $router->post('/users', [Admin\UserController::class, 'store'], ['csrf', 'can:users.create']);
    $router->get('/users/{id:\d+}/edit', [Admin\UserController::class, 'edit'], ['can:users.edit']);
    $router->post('/users/{id:\d+}', [Admin\UserController::class, 'update'], ['csrf', 'can:users.edit']);
    $router->post('/users/{id:\d+}/status', [Admin\UserController::class, 'setStatus'], ['csrf', 'can:users.edit']);
    $router->delete('/users/{id:\d+}', [Admin\UserController::class, 'destroy'], ['csrf', 'can:users.delete']);
    $router->get('/roles', [Admin\RoleController::class, 'index'], ['can:roles.manage'], 'admin.roles');
    $router->post('/roles/{id:\d+}/permissions', [Admin\RoleController::class, 'sync'], ['csrf', 'can:roles.manage']);

    // Taxonomy
    $router->get('/categories', [Admin\CategoryController::class, 'index'], ['can:categories.view'], 'admin.categories');
    $router->post('/categories', [Admin\CategoryController::class, 'store'], ['csrf', 'can:categories.create']);
    $router->post('/categories/{id:\d+}', [Admin\CategoryController::class, 'update'], ['csrf', 'can:categories.edit']);
    $router->delete('/categories/{id:\d+}', [Admin\CategoryController::class, 'destroy'], ['csrf', 'can:categories.delete']);
    $router->post('/subcategories', [Admin\CategoryController::class, 'storeSubcategory'], ['csrf', 'can:categories.create']);
    $router->post('/subcategories/{id:\d+}', [Admin\CategoryController::class, 'updateSubcategory'], ['csrf', 'can:categories.edit']);
    $router->delete('/subcategories/{id:\d+}', [Admin\CategoryController::class, 'destroySubcategory'], ['csrf', 'can:categories.delete']);

    // Templates
    $router->get('/templates', [Admin\TemplateController::class, 'index'], ['can:templates.view'], 'admin.templates');
    $router->get('/templates/create', [Admin\TemplateController::class, 'create'], ['can:templates.create']);
    $router->post('/templates', [Admin\TemplateController::class, 'store'], ['csrf', 'can:templates.create']);
    $router->get('/templates/{id:\d+}/edit', [Admin\TemplateController::class, 'edit'], ['can:templates.edit']);
    $router->post('/templates/{id:\d+}', [Admin\TemplateController::class, 'update'], ['csrf', 'can:templates.edit']);
    $router->post('/templates/{id:\d+}/duplicate', [Admin\TemplateController::class, 'duplicate'], ['csrf', 'can:templates.create']);
    $router->post('/templates/{id:\d+}/toggle', [Admin\TemplateController::class, 'toggle'], ['csrf', 'can:templates.edit']);
    $router->delete('/templates/{id:\d+}', [Admin\TemplateController::class, 'destroy'], ['csrf', 'can:templates.delete']);
    $router->get('/templates/{id:\d+}/preview', [Admin\TemplateController::class, 'preview'], ['can:templates.view']);
    $router->get('/templates/{id:\d+}/fields', [Admin\TemplateFieldController::class, 'index'], ['can:templates.fields']);
    $router->post('/templates/{id:\d+}/fields', [Admin\TemplateFieldController::class, 'store'], ['csrf', 'can:templates.fields']);
    $router->post('/templates/{id:\d+}/fields/{fieldId:\d+}', [Admin\TemplateFieldController::class, 'update'], ['csrf', 'can:templates.fields']);
    $router->post('/templates/{id:\d+}/fields/reorder', [Admin\TemplateFieldController::class, 'reorder'], ['csrf', 'can:templates.fields']);
    $router->delete('/templates/{id:\d+}/fields/{fieldId:\d+}', [Admin\TemplateFieldController::class, 'destroy'], ['csrf', 'can:templates.fields']);
    $router->post('/templates/{id:\d+}/fields/preset', [Admin\TemplateFieldController::class, 'applyPreset'], ['csrf', 'can:templates.fields']);
    $router->get('/templates/{id:\d+}/components', [Admin\TemplateComponentController::class, 'index'], ['can:templates.fields']);
    $router->post('/templates/{id:\d+}/components', [Admin\TemplateComponentController::class, 'store'], ['csrf', 'can:templates.fields']);
    $router->post('/templates/{id:\d+}/components/reorder', [Admin\TemplateComponentController::class, 'reorder'], ['csrf', 'can:templates.fields']);
    $router->post('/templates/{id:\d+}/components/{componentId:\d+}', [Admin\TemplateComponentController::class, 'update'], ['csrf', 'can:templates.fields']);
    $router->delete('/templates/{id:\d+}/components/{componentId:\d+}', [Admin\TemplateComponentController::class, 'destroy'], ['csrf', 'can:templates.fields']);
    $router->get('/templates-generate', [Admin\TemplateController::class, 'generator'], ['can:templates.generate'], 'admin.templates.generate');
    $router->post('/templates-generate', [Admin\TemplateController::class, 'generate'], ['csrf', 'can:templates.generate']);
    $router->post('/templates-generate/remove', [Admin\TemplateController::class, 'removeGenerated'], ['csrf', 'can:templates.delete']);

    // Invitations
    $router->get('/invitations', [Admin\InvitationController::class, 'index'], ['can:invitations.view'], 'admin.invitations');
    $router->get('/invitations/{id:\d+}', [Admin\InvitationController::class, 'show'], ['can:invitations.view']);
    $router->post('/invitations/{id:\d+}/status', [Admin\InvitationController::class, 'setStatus'], ['csrf', 'can:invitations.manage_all']);
    $router->delete('/invitations/{id:\d+}', [Admin\InvitationController::class, 'destroy'], ['csrf', 'can:invitations.delete_any']);

    // Media and fonts
    $router->get('/media', [Admin\MediaController::class, 'index'], ['can:media.view'], 'admin.media');
    $router->post('/media', [Admin\MediaController::class, 'store'], ['csrf', 'can:media.upload']);
    $router->post('/media/{id:\d+}', [Admin\MediaController::class, 'update'], ['csrf', 'can:media.upload']);
    $router->delete('/media/{id:\d+}', [Admin\MediaController::class, 'destroy'], ['csrf', 'can:media.delete']);
    $router->get('/fonts', [Admin\FontController::class, 'index'], ['can:fonts.view'], 'admin.fonts');
    $router->post('/fonts', [Admin\FontController::class, 'store'], ['csrf', 'can:fonts.manage']);
    $router->post('/fonts/{id:\d+}', [Admin\FontController::class, 'update'], ['csrf', 'can:fonts.manage']);
    $router->delete('/fonts/{id:\d+}', [Admin\FontController::class, 'destroy'], ['csrf', 'can:fonts.manage']);

    // Content
    $router->get('/pages', [Admin\PageController::class, 'index'], ['can:pages.view'], 'admin.pages');
    $router->get('/pages/create', [Admin\PageController::class, 'create'], ['can:pages.edit']);
    $router->post('/pages', [Admin\PageController::class, 'store'], ['csrf', 'can:pages.edit']);
    $router->get('/pages/{id:\d+}/edit', [Admin\PageController::class, 'edit'], ['can:pages.edit']);
    $router->post('/pages/{id:\d+}', [Admin\PageController::class, 'update'], ['csrf', 'can:pages.edit']);
    $router->delete('/pages/{id:\d+}', [Admin\PageController::class, 'destroy'], ['csrf', 'can:pages.delete']);

    // Analytics and audit
    $router->get('/analytics', [Admin\AnalyticsController::class, 'index'], ['can:analytics.view'], 'admin.analytics');
    $router->get('/audit', [Admin\AuditController::class, 'index'], ['can:audit.view'], 'admin.audit');

    // Settings
    $router->get('/settings', [Admin\SettingsController::class, 'index'], ['can:settings.view'], 'admin.settings');
    $router->get('/settings/{group:[a-z\-]+}', [Admin\SettingsController::class, 'group'], ['can:settings.view']);
    $router->post('/settings/{group:[a-z\-]+}', [Admin\SettingsController::class, 'update'], ['csrf', 'can:settings.edit']);
    $router->post('/settings-mail/test', [Admin\SettingsController::class, 'testMail'], ['csrf', 'can:settings.edit']);
    $router->get('/flags', [Admin\FeatureFlagController::class, 'index'], ['can:flags.view'], 'admin.flags');
    $router->post('/flags/{key:[a-z0-9_\-]+}', [Admin\FeatureFlagController::class, 'toggle'], ['csrf', 'can:flags.manage']);

    // AI
    $router->get('/ai', [Admin\AiController::class, 'index'], ['can:ai.view'], 'admin.ai');
    $router->post('/ai', [Admin\AiController::class, 'update'], ['csrf', 'can:ai.edit']);
    $router->post('/ai/test', [Admin\AiController::class, 'test'], ['csrf', 'can:ai.edit']);
    $router->post('/ai/clear-key', [Admin\AiController::class, 'clearKey'], ['csrf', 'can:ai.edit']);
    $router->get('/ai/logs', [Admin\AiController::class, 'logs'], ['can:ai.view']);

    // System
    $router->get('/system', [Admin\SystemController::class, 'index'], ['can:health.view'], 'admin.system');
    $router->get('/system/health', [Admin\SystemController::class, 'health'], ['can:health.view'], 'admin.health');
    $router->post('/system/health/run', [Admin\SystemController::class, 'runHealth'], ['csrf', 'can:health.view']);
    $router->get('/system/logs', [Admin\SystemController::class, 'logs'], ['can:logs.view'], 'admin.logs');
    $router->get('/system/logs/view', [Admin\SystemController::class, 'viewLog'], ['can:logs.view']);
    $router->post('/system/cache/clear', [Admin\SystemController::class, 'clearCache'], ['csrf', 'can:settings.edit']);
    $router->get('/system/cron', [Admin\SystemController::class, 'cron'], ['can:health.view'], 'admin.cron');
    $router->post('/system/cron/run', [Admin\SystemController::class, 'runCron'], ['csrf', 'can:cron.run']);

    $router->get('/system/backups', [Admin\BackupController::class, 'index'], ['can:backups.view'], 'admin.backups');
    $router->post('/system/backups', [Admin\BackupController::class, 'store'], ['csrf', 'can:backups.create']);
    $router->get('/system/backups/{id:\d+}/download', [Admin\BackupController::class, 'download'], ['can:backups.view']);
    $router->post('/system/backups/{id:\d+}/restore', [Admin\BackupController::class, 'restore'], ['csrf', 'can:backups.restore']);
    $router->post('/system/backups/{id:\d+}/verify', [Admin\BackupController::class, 'verify'], ['csrf', 'can:backups.view']);
    $router->delete('/system/backups/{id:\d+}', [Admin\BackupController::class, 'destroy'], ['csrf', 'can:backups.delete']);

    $router->get('/system/update', [Admin\UpdateController::class, 'index'], ['can:updates.view'], 'admin.update');
    $router->post('/system/update/source', [Admin\UpdateController::class, 'saveSource'], ['csrf', 'can:updates.apply']);
    $router->post('/system/update/clear-token', [Admin\UpdateController::class, 'clearToken'], ['csrf', 'can:updates.apply']);
    $router->post('/system/update/verify', [Admin\UpdateController::class, 'verify'], ['csrf', 'can:updates.view']);
    $router->post('/system/update/check', [Admin\UpdateController::class, 'check'], ['csrf', 'can:updates.view']);
    $router->post('/system/update/apply', [Admin\UpdateController::class, 'apply'], ['csrf', 'can:updates.apply']);
    $router->post('/system/update/dry-run', [Admin\UpdateController::class, 'dryRun'], ['csrf', 'can:updates.apply']);
    $router->post('/system/update/unlock', [Admin\UpdateController::class, 'unlock'], ['csrf', 'can:updates.apply']);
    $router->post('/system/update/rollback/{id:\d+}', [Admin\UpdateController::class, 'rollback'], ['csrf', 'can:updates.apply']);
    $router->get('/system/update/history', [Admin\UpdateController::class, 'history'], ['can:updates.view']);
    $router->post('/system/maintenance', [Admin\SystemController::class, 'toggleMaintenance'], ['csrf', 'can:settings.edit']);
});

// ---------------------------------------------------------------------------
//  JSON API (v1)
// ---------------------------------------------------------------------------
$router->group('api/v1', ['installed'], static function ($router): void {
    // Public reads
    $router->get('/categories', [Api\CategoryApiController::class, 'index'], ['throttle:api,120,60']);
    $router->get('/templates', [Api\TemplateApiController::class, 'index'], ['throttle:api,120,60']);
    $router->get('/templates/{slug:[a-z0-9\-]+}', [Api\TemplateApiController::class, 'show'], ['throttle:api,120,60']);
    $router->post('/rsvp/{slug:[A-Za-z0-9\-]+}', [Api\RsvpApiController::class, 'store'], ['throttle:api-rsvp,20,3600']);

    // Authentication
    $router->post('/auth/login', [Api\AuthApiController::class, 'login'], ['throttle:api-login,20,600']);
    $router->post('/auth/register', [Api\AuthApiController::class, 'register'], ['throttle:api-register,10,3600']);
    $router->post('/auth/logout', [Api\AuthApiController::class, 'logout'], ['api']);
    $router->get('/auth/me', [Api\AuthApiController::class, 'me'], ['api']);
    $router->post('/auth/tokens', [Api\AuthApiController::class, 'issueToken'], ['api', 'csrf']);
    $router->delete('/auth/tokens/{id:\d+}', [Api\AuthApiController::class, 'revokeToken'], ['api', 'csrf']);

    // Authenticated
    $router->group('', ['api', 'throttle:api,120,60'], static function ($router): void {
        $router->get('/invitations', [Api\InvitationApiController::class, 'index']);
        $router->post('/invitations', [Api\InvitationApiController::class, 'store'], ['csrf']);
        $router->get('/invitations/{id:\d+}', [Api\InvitationApiController::class, 'show']);
        $router->put('/invitations/{id:\d+}', [Api\InvitationApiController::class, 'update'], ['csrf']);
        $router->delete('/invitations/{id:\d+}', [Api\InvitationApiController::class, 'destroy'], ['csrf']);
        $router->post('/invitations/{id:\d+}/publish', [Api\InvitationApiController::class, 'publish'], ['csrf']);
        $router->post('/invitations/{id:\d+}/preview', [Api\InvitationApiController::class, 'preview'], ['csrf']);
        $router->get('/invitations/{id:\d+}/rsvp', [Api\RsvpApiController::class, 'index']);

        $router->get('/analytics/{id:\d+}', [Api\AnalyticsApiController::class, 'show']);
        $router->get('/analytics', [Api\AnalyticsApiController::class, 'overview']);

        $router->post('/ai/wording', [Api\AiApiController::class, 'wording'], ['csrf', 'throttle:ai,20,3600']);
        $router->post('/ai/message', [Api\AiApiController::class, 'message'], ['csrf', 'throttle:ai,20,3600']);
        $router->post('/ai/whatsapp', [Api\AiApiController::class, 'whatsapp'], ['csrf', 'throttle:ai,20,3600']);
        $router->post('/ai/rsvp', [Api\AiApiController::class, 'rsvp'], ['csrf', 'throttle:ai,20,3600']);
        $router->post('/ai/translate', [Api\AiApiController::class, 'translate'], ['csrf', 'throttle:ai,20,3600']);
        $router->post('/ai/recommend', [Api\AiApiController::class, 'recommend'], ['csrf', 'throttle:ai,40,3600']);
    });
});

// ---------------------------------------------------------------------------
//  Scheduled tasks (token protected)
// ---------------------------------------------------------------------------
$router->get('/cron/run', [Web\CronController::class, 'run'], ['installed', 'throttle:cron,30,3600'], 'cron.run');
