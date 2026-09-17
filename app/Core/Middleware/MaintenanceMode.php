<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\MaintenanceService;

/**
 * Serves a friendly maintenance page while an update is running.
 *
 * Administrators keep full access so they can watch the update finish, and
 * /admin/system/* stays reachable to recover from a failure.
 */
final class MaintenanceMode implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $parameters = []): Response
    {
        $maintenance = MaintenanceService::instance();
        if (!$maintenance->isActive()) {
            return $next($request);
        }

        $path = $request->path();
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/login') || str_starts_with($path, '/logout')) {
            return $next($request);
        }
        if (Auth::isAdmin()) {
            return $next($request);
        }
        if ($maintenance->hasBypassToken($request->query('bypass'))) {
            return $next($request);
        }

        $state = $maintenance->state();
        if ($request->expectsJson()) {
            return Response::apiError($state['message'] ?? 'Under maintenance.', 503)
                ->header('Retry-After', '120');
        }

        return View::make('errors.maintenance', [
            'message'    => $state['message'] ?? 'We are performing a short upgrade.',
            'started_at' => $state['started_at'] ?? null,
            'eta'        => $state['eta'] ?? null,
        ])->toResponse(503)->header('Retry-After', '120');
    }
}
