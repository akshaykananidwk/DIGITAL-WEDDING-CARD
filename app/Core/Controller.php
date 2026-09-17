<?php

declare(strict_types=1);

namespace App\Core;

/** Base controller with the small set of helpers every action needs. */
abstract class Controller
{
    protected function view(string $view, array $data = [], int $status = 200): Response
    {
        return View::make($view, $data)->toResponse($status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function success(mixed $data = null, string $message = '', array $meta = []): Response
    {
        return Response::apiSuccess($data, $message, $meta);
    }

    protected function error(string $message, int $status = 422, array $errors = []): Response
    {
        return Response::apiError($message, $status, $errors);
    }

    protected function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(Url::to($path), $status);
    }

    protected function redirectAway(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    /** Redirect to the previous page, keeping input and errors. */
    protected function back(array $errors = [], ?array $input = null): Response
    {
        if ($errors !== []) {
            Session::flashErrors($errors);
        }
        if ($input !== null) {
            Session::flashInput($input);
        }
        $referer = Request::instance()->referer();
        $target = $referer !== '' ? Url::safeRedirect($referer, '/') : Url::to('/');
        return Response::redirect($target);
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    /**
     * Validate request data, redirecting back (or returning JSON) on failure.
     *
     * @throws ValidationException
     */
    protected function validate(Request $request, array $rules, array $labels = [], ?array $data = null): array
    {
        $data ??= $request->all();
        $validator = Validator::make($data, $rules, $labels);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $validator->validated();
    }

    /** Turn a ValidationException into the right response for the request. */
    protected function validationResponse(ValidationException $e, Request $request): Response
    {
        if ($request->expectsJson()) {
            return Response::apiError('Please correct the highlighted fields.', 422, $e->errors());
        }
        return $this->back($e->errors(), $request->all());
    }

    /** Paginate helper that also returns the rendered page links data. */
    protected function paginationMeta(array $result, string $baseUrl, array $query = []): array
    {
        $build = static function (int $page) use ($baseUrl, $query): string {
            $query['page'] = $page;
            return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . http_build_query($query);
        };
        return [
            'page'     => $result['page'],
            'pages'    => $result['pages'],
            'total'    => $result['total'],
            'per_page' => $result['per_page'],
            'prev'     => $result['page'] > 1 ? $build($result['page'] - 1) : null,
            'next'     => $result['page'] < $result['pages'] ? $build($result['page'] + 1) : null,
            'build'    => $build,
        ];
    }
}
