<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/** Route middleware: can:templates.create */
final class RequirePermission implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $parameters = []): Response
    {
        $permission = $parameters[0] ?? '';
        if ($permission !== '') {
            Auth::authorize($permission);
        }
        return $next($request);
    }
}
