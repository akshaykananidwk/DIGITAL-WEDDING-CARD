<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Core\Version;
use App\Services\AuditService;
use App\Services\GitHubService;
use App\Services\UpdateService;

/**
 * System → Updates.
 *
 * The repository, branch and token are entered once. After that, checking and
 * applying an update is two buttons, and a failed update restores itself.
 */
final class UpdateController extends AdminController
{
    public function __construct(
        private readonly UpdateService $updates = new UpdateService(),
        private readonly GitHubService $github = new GitHubService()
    ) {
    }

    public function index(Request $request): Response
    {
        $cachedCheck = Cache::get('update:last_check');

        return $this->admin('admin.system.update', 'Updates', [
            'state'       => $this->updates->state(),
            'lastCheck'   => is_array($cachedCheck) ? $cachedCheck : null,
            'history'     => $this->updates->history(1, 10)['rows'],
            'version'     => Version::current(),
            'installedVersion' => Version::installed(),
            'canZip'      => class_exists(\ZipArchive::class),
            'hasCurl'     => function_exists('curl_init'),
        ]);
    }

    public function saveSource(Request $request): Response
    {
        $result = $this->github->saveConfiguration(
            (string) $request->input('repository', ''),
            (string) $request->input('branch', 'main'),
            (string) $request->raw('token', ''),
            Auth::id()
        );

        if (!$result['ok']) {
            return $this->respond($request, false, (string) $result['message'], 'admin/system/update');
        }

        // Confirm it actually works before telling the operator it is saved.
        $verification = $this->github->verify();
        $message = (string) $result['message'] . ' ' . (string) $verification['message'];

        return $this->respond($request, (bool) $verification['ok'], $message, 'admin/system/update');
    }

    public function verify(Request $request): Response
    {
        $result = $this->github->verify();
        $rate = $this->github->rateLimit();

        return $this->respond(
            $request,
            (bool) $result['ok'],
            (string) $result['message']
            . ($rate['limit'] > 0 ? ' API calls remaining: ' . $rate['remaining'] . '/' . $rate['limit'] . '.' : ''),
            'admin/system/update',
            ['repository' => $result['repository']]
        );
    }

    public function check(Request $request): Response
    {
        $result = $this->updates->check();
        Cache::put('update:last_check', $result, 900);

        AuditService::instance()->log(
            'admin.update.check',
            'update',
            null,
            $result['update_available'] ? 'Update available' : 'Up to date'
        );

        if ($request->expectsJson()) {
            return $result['ok']
                ? $this->success($result, (string) $result['message'])
                : $this->error((string) $result['message'], 422, $result);
        }

        $this->flash(
            $result['ok'] ? ($result['update_available'] ? 'info' : 'success') : 'danger',
            (string) $result['message']
        );
        return $this->redirect('admin/system/update');
    }

    public function dryRun(Request $request): Response
    {
        $result = $this->updates->apply(true);

        return $this->respond(
            $request,
            (bool) $result['ok'],
            (string) $result['message'],
            'admin/system/update',
            ['steps' => $result['steps'], 'log_id' => $result['log_id']]
        );
    }

    public function apply(Request $request): Response
    {
        if ((string) $request->input('confirm') !== 'UPDATE') {
            return $this->respond(
                $request,
                false,
                'Type UPDATE to confirm. A backup is taken automatically before anything changes.',
                'admin/system/update'
            );
        }

        // An update can outlive the default limit on a slow host.
        @set_time_limit(900);
        @ini_set('memory_limit', '512M');

        $result = $this->updates->apply(false);

        if ($request->expectsJson()) {
            return $result['ok']
                ? $this->success(
                    ['steps' => $result['steps'], 'log_id' => $result['log_id']],
                    (string) $result['message']
                )
                : $this->error((string) $result['message'], 500, [
                    'steps'       => $result['steps'],
                    'rolled_back' => $result['rolled_back'],
                ]);
        }

        $this->flash($result['ok'] ? 'success' : 'danger', (string) $result['message']);
        return $this->redirect('admin/system/update');
    }

    public function unlock(Request $request): Response
    {
        $result = $this->updates->forceUnlock();
        return $this->respond($request, true, (string) $result['message'], 'admin/system/update');
    }

    public function rollback(Request $request): Response
    {
        if ((string) $request->input('confirm') !== 'ROLLBACK') {
            return $this->respond(
                $request,
                false,
                'Type ROLLBACK to confirm - this restores the files and database from that backup.',
                'admin/system/update'
            );
        }

        @set_time_limit(900);
        $result = $this->updates->rollbackTo($request->int('id'));

        return $this->respond($request, (bool) $result['ok'], (string) $result['message'], 'admin/system/update');
    }

    public function history(Request $request): Response
    {
        $result = $this->updates->history(max(1, $request->int('page', 1)), 20);

        return $this->admin('admin.system.update-history', 'Update history', [
            'entries'    => $result['rows'],
            'pagination' => $this->paginationMeta($result, Url::to('admin/system/update/history')),
        ]);
    }
}
