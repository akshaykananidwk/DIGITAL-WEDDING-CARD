<?php

declare(strict_types=1);

namespace App\Controllers\Install;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Services\InstallService;

/**
 * The installer.
 *
 * Runs before any configuration exists, so it cannot rely on the database,
 * settings or the normal middleware stack. Once installation finishes it
 * refuses to run again: /install then simply reports
 * "Application already installed."
 */
final class InstallController extends Controller
{
    public function __construct(
        private readonly InstallService $installer = new InstallService()
    ) {
    }

    public function index(Request $request): Response
    {
        if ($this->installer->isLocked()) {
            return $this->alreadyInstalled();
        }

        return $this->view('install.index', [
            'requirements' => $this->installer->requirements(),
            'defaults'     => $this->installer->defaults(),
            'csrf'         => Csrf::token(),
            'phpVersion'   => PHP_VERSION,
            'appVersion'   => \App\Core\Version::current(),
        ]);
    }

    /** Re-run the requirement checks without reloading the page. */
    public function requirements(Request $request): Response
    {
        if ($this->installer->isLocked()) {
            return $this->error('Application already installed.', 403);
        }
        $this->verifyInstallerToken();

        return $this->success($this->installer->requirements());
    }

    /** Step 2: test the database credentials. */
    public function testDatabase(Request $request): Response
    {
        if ($this->installer->isLocked()) {
            return $this->error('Application already installed.', 403);
        }
        $this->verifyInstallerToken();

        $result = $this->installer->testDatabase([
            'driver'   => $request->input('db_driver', 'mysql'),
            'host'     => $request->input('db_host', '127.0.0.1'),
            'port'     => $request->int('db_port', 3306),
            'database' => $request->input('db_database', ''),
            'username' => $request->input('db_username', ''),
            'password' => $request->raw('db_password', ''),
            'prefix'   => $request->input('db_prefix', ''),
            'socket'   => $request->input('db_socket', ''),
        ]);

        return $result['ok']
            ? $this->success($result, (string) $result['message'])
            : $this->error((string) $result['message'], 422);
    }

    /** Step 3: run the installation. */
    public function install(Request $request): Response
    {
        if ($this->installer->isLocked()) {
            return $request->expectsJson()
                ? $this->error('Application already installed.', 403)
                : $this->alreadyInstalled();
        }
        $this->verifyInstallerToken();

        $input = [
            'db_driver'   => $request->input('db_driver', 'mysql'),
            'db_host'     => $request->input('db_host', '127.0.0.1'),
            'db_port'     => $request->int('db_port', 3306),
            'db_database' => $request->input('db_database', ''),
            'db_username' => $request->input('db_username', ''),
            'db_password' => $request->raw('db_password', ''),
            'db_prefix'   => $request->input('db_prefix', ''),
            'db_socket'   => $request->input('db_socket', ''),

            'site_name'   => $request->input('site_name', 'Shubh Kankotri'),
            'site_url'    => $request->input('site_url', ''),
            'locale'      => $request->input('locale', 'en'),

            'admin_name'  => $request->input('admin_name', ''),
            'admin_email' => $request->input('admin_email', ''),
            'admin_phone' => $request->input('admin_phone', ''),
            'admin_password' => $request->raw('admin_password', ''),
            'admin_password_confirmation' => $request->raw('admin_password_confirmation', ''),

            'template_count' => $request->int('template_count', 250),
            'create_demo'    => $request->bool('create_demo', true),
        ];

        $result = $this->installer->install($input);

        if (!$result['ok']) {
            Logger::warning('Installation attempt failed: ' . $result['message']);
            return $request->expectsJson()
                ? $this->error($result['message'], 422, ['steps' => $result['steps']])
                : $this->view('install.index', [
                    'requirements' => $this->installer->requirements(),
                    'defaults'     => array_merge($this->installer->defaults(), [
                        'db_host'     => $input['db_host'],
                        'db_port'     => $input['db_port'],
                        'db_database' => $input['db_database'],
                        'db_username' => $input['db_username'],
                        'site_name'   => $input['site_name'],
                        'site_url'    => $input['site_url'],
                        'admin_name'  => $input['admin_name'],
                        'admin_email' => $input['admin_email'],
                    ]),
                    'csrf'       => Csrf::token(),
                    'error'      => $result['message'],
                    'steps'      => $result['steps'],
                    'phpVersion' => PHP_VERSION,
                    'appVersion' => \App\Core\Version::current(),
                ], 422);
        }

        // The session was started before the app key existed; start clean.
        Session::destroy();

        if ($request->expectsJson()) {
            return $this->success([
                'steps'    => $result['steps'],
                'redirect' => Url::to('/login'),
            ], $result['message']);
        }

        return $this->view('install.complete', [
            'steps'       => $result['steps'],
            'adminEmail'  => $result['admin_email'],
            'loginUrl'    => Url::to('/login'),
            'adminUrl'    => Url::to('/admin'),
            'siteUrl'     => Url::base(),
        ]);
    }

    private function alreadyInstalled(): Response
    {
        return $this->view('install.locked', [
            'loginUrl' => Url::to('/login'),
            'siteUrl'  => Url::base(),
        ], 403);
    }

    /**
     * CSRF for the installer.
     *
     * The normal middleware is not in the chain here (the app is not
     * installed), so the check is explicit - and rate limited, because this
     * endpoint is reachable by anyone until installation completes.
     */
    private function verifyInstallerToken(): void
    {
        RateLimiter::enforce('installer', Request::clientIp(), 40, 600);
        Csrf::verify();
    }
}
