<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;

/** Admin panel gate: authenticated *and* holding an admin-capable role. */
final class AdminOnly implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $parameters = []): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return Response::apiError('Authentication required.', 401);
            }
            Session::set('_intended', $request->path());
            return Response::redirect(Url::to('/login'));
        }

        if (!Auth::isAdmin()) {
            Logger::security('Non-admin attempted to access the admin panel', [
                'user_id' => Auth::id(),
                'path'    => $request->path(),
            ]);
            throw HttpException::forbidden('The admin area is restricted.');
        }

        return $next($request);
    }
}
