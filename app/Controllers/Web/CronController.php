<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\CronService;

/**
 * URL-triggered cron, for hosts where a real crontab is not available.
 *
 * Protected by a rotating token; an invalid token is logged as a security
 * event and answered with 404 so the endpoint is not discoverable.
 */
final class CronController extends Controller
{
    public function run(Request $request): Response
    {
        $cron = new CronService();

        if (!$cron->verifyToken((string) $request->query('token', ''))) {
            Logger::security('Cron endpoint called with an invalid token');
            throw HttpException::notFound();
        }

        $task = (string) $request->query('task', 'all');
        if ($task !== 'all' && !array_key_exists($task, CronService::TASKS)) {
            return Response::text('Unknown task: ' . $task, 422);
        }

        $results = $cron->run($task);

        $lines = [];
        $failed = 0;
        foreach ($results as $name => $result) {
            $lines[] = ($result['ok'] ? '[ok]   ' : '[fail] ') . $name . ': ' . $result['message']
                . ' (' . $result['duration_ms'] . 'ms)';
            if (!$result['ok']) {
                $failed++;
            }
        }

        if ($request->expectsJson()) {
            return Response::json(['success' => $failed === 0, 'results' => $results], $failed === 0 ? 200 : 500);
        }

        return Response::text(implode("\n", $lines) . "\n", $failed === 0 ? 200 : 500);
    }
}
