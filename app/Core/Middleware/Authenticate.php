<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;

/** Requires a logged-in user; remembers where they were heading. */
final class Authenticate implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $parameters = []): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return Response::apiError('Authentication required.', 401);
        }

        Session::set('_intended', $request->path());
        Session::flash('info', Session::get('_expired') === true
            ? 'Your session expired. Please sign in again.'
            : 'Please sign in to continue.');
        Session::forget('_expired');

        return Response::redirect(Url::to('/login'));
    }
}
