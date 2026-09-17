<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;

interface MiddlewareInterface
{
    /**
     * Handle the request, optionally short-circuiting with your own response.
     *
     * @param callable(Request):Response $next
     */
    public function handle(Request $request, callable $next, array $parameters = []): Response;
}
