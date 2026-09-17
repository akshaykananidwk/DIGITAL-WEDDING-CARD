<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

/**
 * CSRF check for every state-changing request.
 *
 * Exempt paths (public RSVP posts, analytics beacons) are declared on the
 * route itself rather than in a global list, so nothing is exempt by accident.
 */
final class VerifyCsrf implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, callable $next, array $parameters = []): Response
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        // A bearer token is not sent automatically by a browser, so a hostile
        // page cannot make the request in the first place: there is nothing
        // for a CSRF token to add. Cookie-authenticated requests, which are
        // the vulnerable ones, are always checked.
        if (Auth::isTokenAuthenticated()) {
            return $next($request);
        }

        Csrf::verify();
        return $next($request);
    }
}
