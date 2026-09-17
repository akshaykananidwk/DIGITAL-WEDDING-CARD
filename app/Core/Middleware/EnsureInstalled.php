<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;

/** Sends every request to /install until the installer has finished. */
final class EnsureInstalled implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $parameters = []): Response
    {
        if (Config::isInstalled()) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            return Response::apiError('The application is not installed yet.', 503);
        }
        return Response::redirect(Url::to('/install'));
    }
}
