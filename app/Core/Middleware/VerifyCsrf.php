<?php

declare(strict_types=1);

namespace App\Core\Middleware;

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
        Csrf::verify();
        return $next($request);
    }
}
