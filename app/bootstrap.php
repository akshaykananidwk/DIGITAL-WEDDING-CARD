<?php
/**
 * Application bootstrap: autoloading, error handling, configuration.
 *
 * Kept deliberately dependency free so the installer (which runs before any
 * configuration exists) can use the very same bootstrap path.
 */

declare(strict_types=1);

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    http_response_code(500);
    exit('This application requires PHP 8.0 or newer. Detected: ' . PHP_VERSION);
}

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('DATABASE_PATH', ROOT_PATH . '/database');
define('ASSET_PATH', ROOT_PATH . '/assets');

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
date_default_timezone_set('Asia/Kolkata');

// ---------------------------------------------------------------------------
// PSR-4 style autoloader for the App\ namespace. No Composer required, but a
// Composer autoloader is used when present (optional PDF/mail libraries).
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}

require APP_PATH . '/Core/Support/helpers.php';

App\Core\ErrorHandler::register();
