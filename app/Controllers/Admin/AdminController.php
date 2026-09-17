<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\SeoService;

/** Shared helpers for the admin panel. */
abstract class AdminController extends Controller
{
    /** Render an admin view with the panel chrome and a noindex head. */
    protected function admin(string $view, string $title, array $data = [], int $status = 200): Response
    {
        return $this->view($view, array_merge([
            'seo'        => SeoService::make()->title($title . ' · Admin')->noindex(),
            'pageTitle'  => $title,
        ], $data), $status);
    }

    /** Respond correctly whether the request was AJAX or a normal form post. */
    protected function respond(Request $request, bool $ok, string $message, string $redirect, array $payload = []): Response
    {
        if ($request->expectsJson()) {
            return $ok
                ? $this->success($payload === [] ? null : $payload, $message)
                : $this->error($message, 422, $payload);
        }
        $this->flash($ok ? 'success' : 'danger', $message);
        return $this->redirect($redirect);
    }
}
