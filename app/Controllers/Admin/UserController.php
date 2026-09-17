<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Url;
use App\Core\ValidationException;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;

final class UserController extends AdminController
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly RoleRepository $roles = new RoleRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'q'      => mb_substr((string) $request->query('q', ''), 0, 80),
            'role'   => (string) $request->query('role', ''),
            'status' => in_array($request->query('status'), ['active', 'pending', 'suspended'], true)
                ? (string) $request->query('status')
                : '',
        ];

        $result = $this->users->search($filters, max(1, $request->int('page', 1)), 25);

        return $this->admin('admin.users.index', 'Users', [
            'users'      => $result['rows'],
            'pagination' => $this->paginationMeta($result, Url::to('admin/users'), array_filter($filters)),
            'filters'    => $filters,
            'roles'      => $this->roles->withUserCounts(),
            'stats'      => $this->users->stats(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->admin('admin.users.form', 'Add user', [
            'user'  => null,
            'roles' => $this->roles->all('level DESC'),
        ]);
    }

    public function store(Request $request): Response
    {
        try {
            $data = $this->validate($request, [
                'name'     => 'required|string|min:2|max:120|no_html',
                'email'    => 'required|email|unique:users,email',
                'phone'    => 'nullable|phone',
                'password' => 'required|password',
                'role_id'  => 'required|integer|exists:roles,id',
                'status'   => 'required|in:active,pending,suspended',
            ]);
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $this->assertRoleAssignable((int) $data['role_id']);

        $userId = $this->users->create([
            'name'     => (string) $data['name'],
            'email'    => strtolower((string) $data['email']),
            'phone'    => isset($data['phone']) ? Str::phone((string) $data['phone']) : null,
            'password' => Auth::hash((string) $request->raw('password', '')),
            'role_id'  => (int) $data['role_id'],
            'status'   => (string) $data['status'],
            'locale'   => 'en',
            'email_verified_at' => Database::now(),
        ]);

        AuditService::instance()->log('admin.user.create', 'user', $userId, (string) $data['email']);

        return $this->respond($request, true, 'User created.', 'admin/users');
    }

    public function edit(Request $request): Response
    {
        $user = $this->users->find($request->int('id'));
        if ($user === null) {
            throw HttpException::notFound('That user does not exist.');
        }
        $this->assertCanManage($user);

        return $this->admin('admin.users.form', 'Edit user', [
            'user'  => $user,
            'roles' => $this->roles->all('level DESC'),
        ]);
    }

    public function update(Request $request): Response
    {
        $userId = $request->int('id');
        $user = $this->users->find($userId);
        if ($user === null) {
            throw HttpException::notFound();
        }
        $this->assertCanManage($user);

        try {
            $data = $this->validate($request, [
                'name'    => 'required|string|min:2|max:120|no_html',
                'email'   => 'required|email|unique:users,email,' . $userId,
                'phone'   => 'nullable|phone',
                'role_id' => 'required|integer|exists:roles,id',
                'status'  => 'required|in:active,pending,suspended',
                'password' => 'nullable|password',
            ]);
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $this->assertRoleAssignable((int) $data['role_id']);

        $update = [
            'name'    => (string) $data['name'],
            'email'   => strtolower((string) $data['email']),
            'phone'   => isset($data['phone']) ? Str::phone((string) $data['phone']) : null,
            'role_id' => (int) $data['role_id'],
            'status'  => (string) $data['status'],
        ];
        $newPassword = (string) $request->raw('password', '');
        if ($newPassword !== '') {
            $update['password'] = Auth::hash($newPassword);
            $this->users->clearRememberToken($userId);
        }

        $this->users->update($userId, $update);
        AuditService::instance()->logChanges('admin.user.update', 'user', $userId, $user, $update);

        return $this->respond($request, true, 'User updated.', 'admin/users');
    }

    public function setStatus(Request $request): Response
    {
        $userId = $request->int('id');
        $user = $this->users->find($userId);
        if ($user === null) {
            throw HttpException::notFound();
        }
        $this->assertCanManage($user);

        $status = (string) $request->input('status');
        if (!in_array($status, ['active', 'pending', 'suspended'], true)) {
            return $this->respond($request, false, 'Unknown status.', 'admin/users');
        }
        if ($userId === Auth::id() && $status !== 'active') {
            return $this->respond($request, false, 'You cannot suspend your own account.', 'admin/users');
        }

        $this->users->update($userId, ['status' => $status]);
        if ($status !== 'active') {
            $this->users->clearRememberToken($userId);
        }
        AuditService::instance()->log('admin.user.status', 'user', $userId, 'Status set to ' . $status);

        return $this->respond($request, true, 'Status updated.', 'admin/users');
    }

    public function destroy(Request $request): Response
    {
        $userId = $request->int('id');
        $user = $this->users->find($userId);
        if ($user === null) {
            throw HttpException::notFound();
        }
        $this->assertCanManage($user);

        if ($userId === Auth::id()) {
            return $this->respond($request, false, 'You cannot delete your own account here.', 'admin/users');
        }

        $this->users->delete($userId);
        $this->users->clearRememberToken($userId);
        AuditService::instance()->log('admin.user.delete', 'user', $userId, (string) $user['email']);

        return $this->respond($request, true, 'User deleted.', 'admin/users');
    }

    /**
     * An admin may not edit someone with an equal or higher role level, and
     * may not hand out a role above their own.
     */
    private function assertCanManage(array $user): void
    {
        if (Auth::isSuperAdmin()) {
            return;
        }
        $actor = Auth::user() ?? [];
        $actorLevel = (int) ($actor['role_level'] ?? 0);
        $targetLevel = (int) ($user['role_level'] ?? 0);

        if ($targetLevel >= $actorLevel && (int) $user['id'] !== (int) ($actor['id'] ?? 0)) {
            throw HttpException::forbidden('You cannot manage an account at or above your own role level.');
        }
    }

    private function assertRoleAssignable(int $roleId): void
    {
        if (Auth::isSuperAdmin()) {
            return;
        }
        $role = $this->roles->find($roleId);
        $actor = Auth::user() ?? [];
        if ($role === null || (int) $role['level'] >= (int) ($actor['role_level'] ?? 0)) {
            throw HttpException::forbidden('You cannot assign that role.');
        }
    }
}
