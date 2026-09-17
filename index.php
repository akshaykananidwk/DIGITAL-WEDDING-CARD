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

// A single byte of output before headers are sent breaks redirects, PDF and
// image responses, so buffer everything the framework emits.
ob_start();

require __DIR__ . '/app/bootstrap.php';

App\Core\Application::getInstance()->run();
