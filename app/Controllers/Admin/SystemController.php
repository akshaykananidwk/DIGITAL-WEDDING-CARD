<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Cache;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Version;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\CronService;
use App\Services\HealthService;
use App\Services\MaintenanceService;
use App\Services\UpdateService;

final class SystemController extends AdminController
{
    public function __construct(
        private readonly HealthService $health = new HealthService()
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->admin('admin.system.index', 'System', [
            'meta'        => $this->health->meta(),
            'latestHealth' => $this->health->latest(),
            'backup'      => (new BackupService())->stats(),
            'update'      => (new UpdateService())->state(),
            'maintenance' => (new MaintenanceService())->isActive(),
            'cron'        => (new CronService())->status(),
            'logs'        => array_slice(Logger::files(), 0, 6),
            'version'     => Version::current(),
        ]);
    }

    public function health(Request $request): Response
    {
        $result = $this->health->run('manual', false);

        return $this->admin('admin.system.health', 'System health', [
            'result'  => $result,
            'meta'    => $result['meta'],
            'history' => $this->health->history(10),
        ]);
    }

    public function runHealth(Request $request): Response
    {
        $result = $this->health->run('manual');
        AuditService::instance()->log('admin.health.run', 'system', null, 'Status: ' . $result['status']);

        if ($request->expectsJson()) {
            return $this->success($result, 'Health check complete: ' . $result['status'] . '.');
        }
        $this->flash(
            $result['status'] === HealthService::OK ? 'success' : ($result['status'] === HealthService::WARNING ? 'warning' : 'danger'),
            'Health check complete: ' . $result['status'] . '.'
        );
        return $this->redirect('admin/system/health');
    }

    public function logs(Request $request): Response
    {
        return $this->admin('admin.system.logs', 'Logs', [
            'files' => Logger::files(),
        ]);
    }

    public function viewLog(Request $request): Response
    {
        // basename() inside tail() is what keeps this off the filesystem.
        $file = (string) $request->query('file', '');
        $lines = max(50, min(2000, $request->int('lines', 300)));
        $content = Logger::tail($file, $lines);

        if ($request->expectsJson()) {
            return $this->success(['file' => basename($file), 'content' => $content]);
        }

        return $this->admin('admin.system.log-view', 'Log · ' . basename($file), [
            'file'    => basename($file),
            'content' => $content,
            'lines'   => $lines,
            'files'   => Logger::files(),
        ]);
    }

    public function clearCache(Request $request): Response
    {
        $result = (new UpdateService())->clearCaches();
        AuditService::instance()->log('admin.cache.clear', 'system', null, $result['message']);

        return $this->respond($request, true, (string) $result['message'], 'admin/system');
    }

    public function cron(Request $request): Response
    {
        $cron = new CronService();

        return $this->admin('admin.system.cron', 'Scheduled tasks', [
            'tasks'        => $cron->status(),
            'instructions' => $cron->instructions(),
            'recent'       => $cron->recentRuns(20),
            'definitions'  => CronService::TASKS,
        ]);
    }

    public function runCron(Request $request): Response
    {
        $task = (string) $request->input('task', 'all');
        if ($task !== 'all' && !array_key_exists($task, CronService::TASKS)) {
            return $this->respond($request, false, 'Unknown task.', 'admin/system/cron');
        }

        $results = (new CronService())->run($task);
        $failed = count(array_filter($results, static fn (array $r): bool => !$r['ok']));

        AuditService::instance()->log('admin.cron.run', 'system', null, $task . ' (' . count($results) . ' task(s))');

        $summary = [];
        foreach ($results as $name => $result) {
            $summary[] = $name . ': ' . $result['message'];
        }

        return $this->respond(
            $request,
            $failed === 0,
            implode(' | ', $summary),
            'admin/system/cron',
            ['results' => $results]
        );
    }

    public function toggleMaintenance(Request $request): Response
    {
        $maintenance = new MaintenanceService();

        if ($maintenance->isActive()) {
            $maintenance->disable();
            AuditService::instance()->log('admin.maintenance.off', 'system');
            return $this->respond($request, true, 'Maintenance mode is off. The site is live again.', 'admin/system');
        }

        $message = mb_substr(trim(strip_tags((string) $request->input('message', ''))), 0, 300);
        $token = $maintenance->enable($message);
        AuditService::instance()->log('admin.maintenance.on', 'system', null, $message);

        return $this->respond(
            $request,
            true,
            'Maintenance mode is on. Administrators still have full access. Bypass link token: ' . $token,
            'admin/system'
        );
    }
}
