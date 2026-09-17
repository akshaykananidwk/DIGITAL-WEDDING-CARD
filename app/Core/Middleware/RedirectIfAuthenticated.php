<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;

/** Keeps logged-in users away from the login/register screens. */
final class RedirectIfAuthenticated implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $parameters = []): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }
        return Response::redirect(Url::to(Auth::isAdmin() ? '/admin' : '/dashboard'));
    }
}
