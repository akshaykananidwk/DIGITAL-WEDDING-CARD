<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ApiTokenRepository;

/**
 * Authenticates an /api/v1 request.
 *
 * Accepts either the browser session (so the builder's AJAX calls work) or a
 * bearer token, which is what a future mobile app will use. Tokens are stored
 * hashed; the plaintext exists only once, at creation time.
 */
final class ApiAuth implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $parameters = []): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        $header = $request->header('authorization');
        if (stripos($header, 'bearer ') === 0) {
            $token = trim(substr($header, 7));
            $user = (new ApiTokenRepository())->resolveUser($token);
            if (is_array($user)) {
                Auth::login($user);
                return $next($request);
            }
        }

        return Response::apiError('Authentication required.', 401);
    }
}
