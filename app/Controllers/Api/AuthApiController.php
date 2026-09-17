<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repositories\ApiTokenRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\FeatureFlagService;
use App\Services\SettingsService;

/**
 * API authentication.
 *
 * Login returns a bearer token for a future mobile app; the browser keeps
 * using its session, so the same endpoints serve both.
 */
final class AuthApiController extends Controller
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly ApiTokenRepository $tokens = new ApiTokenRepository()
    ) {
    }

    public function login(Request $request): Response
    {
        try {
            $data = $this->validate($request, [
                'email'    => 'required|email',
                'password' => 'required|string|max:200',
            ]);
        } catch (ValidationException $e) {
            return $this->error('Invalid credentials.', 422, $e->errors());
        }

        $result = Auth::attempt((string) $data['email'], (string) $request->raw('password', ''));
        if (!$result['ok']) {
            return $this->error($result['message'], 401);
        }

        $user = (array) $result['user'];
        $issued = $this->tokens->issue(
            (int) $user['id'],
            'API login ' . date('Y-m-d H:i'),
            ['*'],
            30
        );

        return $this->success([
            'token'      => $issued['token'],
            'token_type' => 'Bearer',
            'expires_in' => 2592000,
            'user'       => $this->transformUser($user),
        ], 'Signed in.');
    }

    public function register(Request $request): Response
    {
        if (!SettingsService::instance()->bool('registration_open', true)
            || FeatureFlagService::instance()->disabled('registration', true)) {
            return $this->error('Registrations are closed.', 403);
        }

        try {
            $data = $this->validate($request, [
                'name'     => 'required|string|min:2|max:120|no_html',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|password',
            ]);
        } catch (ValidationException $e) {
            return $this->error('Please correct the highlighted fields.', 422, $e->errors());
        }

        $role = (new RoleRepository())->findBySlug('user');
        if ($role === null) {
            return $this->error('Registration is unavailable.', 503);
        }

        $userId = $this->users->create([
            'name'     => (string) $data['name'],
            'email'    => strtolower((string) $data['email']),
            'password' => Auth::hash((string) $request->raw('password', '')),
            'role_id'  => (int) $role['id'],
            'status'   => 'active',
            'locale'   => Lang::locale(),
            'email_verified_at' => \App\Core\Database::now(),
        ]);

        $user = $this->users->find($userId);
        if ($user === null) {
            return $this->error('The account could not be created.', 500);
        }

        $issued = $this->tokens->issue($userId, 'API registration', ['*'], 30);

        return $this->success([
            'token'      => $issued['token'],
            'token_type' => 'Bearer',
            'user'       => $this->transformUser($user),
        ], 'Account created.');
    }

    public function me(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $this->error('Authentication required.', 401);
        }
        return $this->success($this->transformUser($user));
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        return $this->success(null, 'Signed out.');
    }

    public function issueToken(Request $request): Response
    {
        $name = mb_substr((string) $request->input('name', 'API token'), 0, 80);
        $issued = $this->tokens->issue((int) Auth::id(), $name, ['*'], 365);

        return $this->success([
            'id'    => $issued['id'],
            'name'  => $name,
            'token' => $issued['token'],
        ], 'Token created. Copy it now - it is not shown again.');
    }

    public function revokeToken(Request $request): Response
    {
        $removed = $this->tokens->revoke($request->int('id'), (int) Auth::id());
        return $removed > 0
            ? $this->success(null, 'Token revoked.')
            : $this->error('That token does not exist.', 404);
    }

    /** @return array<string,mixed> */
    private function transformUser(array $user): array
    {
        return [
            'id'     => (int) $user['id'],
            'name'   => (string) $user['name'],
            'email'  => (string) $user['email'],
            'role'   => (string) ($user['role_slug'] ?? 'user'),
            'locale' => (string) $user['locale'],
            'plan'   => (string) ($user['plan_slug'] ?? 'free'),
            'invitations' => (int) $user['invitation_count'],
        ];
    }
}
