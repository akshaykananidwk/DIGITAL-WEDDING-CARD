<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;

/** Route middleware: throttle:bucket,max,windowSeconds */
final class ThrottleRequests implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, array $parameters = []): Response
    {
        $bucket = (string) ($parameters[0] ?? 'global');
        $max = (int) ($parameters[1] ?? 60);
        $window = (int) ($parameters[2] ?? 60);

        $identity = Auth::id() !== null ? 'u' . Auth::id() : 'ip' . Request::clientIp();
        $result = RateLimiter::enforce($bucket, $identity, $max, $window);

        $response = $next($request);

        return $response
            ->header('X-RateLimit-Limit', (string) $max)
            ->header('X-RateLimit-Remaining', (string) $result['remaining']);
    }
}
