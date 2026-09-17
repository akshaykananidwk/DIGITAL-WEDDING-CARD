<?php
/**
 * Invitation Card SaaS - Front Controller
 *
 * Every public request enters here. The application requires nothing but a
 * standard PHP 8 + MySQL host (Apache/LiteSpeed/nginx with a rewrite to this
 * file). No Node.js runtime, no build step.
 *
 * @package InvitationSaaS
 */

declare(strict_types=1);

define('APP_START', microtime(true));
define('ROOT_PATH', __DIR__);

/*
 * PHP's built-in server has no rewrite rules, so when this file is used as its
 * router script (`php -S localhost:8000 index.php`) it also has to hand back
 * real files - assets, fonts, uploads - the way Apache does through
 * .htaccess. The same deny list as .htaccess applies, so the application's
 * internals stay unreachable either way.
 */
if (PHP_SAPI === 'cli-server') {
    $requested = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $relative = ltrim(urldecode($requested), '/');
    $blocked = $relative === ''
        || str_contains($relative, '..')
        || preg_match('#^(app|database|storage|tests|bin|docs|vendor)(/|$)#', $relative) === 1
        || preg_match('#(^|/)\.#', $relative) === 1
        || preg_match('#\.(php|phtml|phar|ini|log|sql|md|json|lock)$#i', $relative) === 1;

    if (!$blocked) {
        $candidate = realpath(__DIR__ . '/' . $relative);
        if ($candidate !== false && is_file($candidate) && str_starts_with($candidate, __DIR__ . DIRECTORY_SEPARATOR)) {
            return false; // Let the built-in server serve it with its own MIME type.
        }
    }
}

// A single byte of output before headers are sent breaks redirects, PDF and
// image responses, so buffer everything the framework emits.
ob_start();

require __DIR__ . '/app/bootstrap.php';

App\Core\Application::getInstance()->run();
