<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Services\AuditService;

final class RoleController extends AdminController
{
    public function __construct(
        private readonly RoleRepository $roles = new RoleRepository(),
        private readonly PermissionRepository $permissions = new PermissionRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        $roles = $this->roles->withUserCounts();
        $granted = [];
        foreach ($roles as $role) {
            $granted[(int) $role['id']] = $this->roles->permissionSlugs((int) $role['id']);
        }

        return $this->admin('admin.roles', 'Roles & permissions', [
            'roles'       => $roles,
            'permissions' => $this->permissions->grouped(),
            'granted'     => $granted,
        ]);
    }

    public function sync(Request $request): Response
    {
        if (!Auth::isSuperAdmin()) {
            throw HttpException::forbidden('Only a super admin can change permissions.');
        }

        $roleId = $request->int('id');
        $role = $this->roles->find($roleId);
        if ($role === null) {
            throw HttpException::notFound();
        }
        if ((string) $role['slug'] === 'super-admin') {
            return $this->respond($request, false, 'The super admin role always holds every permission.', 'admin/roles');
        }

        $slugs = array_values(array_filter(array_map(
            static fn ($slug): string => preg_replace('/[^a-z0-9_.*\-]/i', '', (string) $slug) ?? '',
            $request->array('permissions')
        )));

        $this->roles->syncPermissions($roleId, $slugs);
        AuditService::instance()->log(
            'admin.role.permissions',
            'role',
            $roleId,
            count($slugs) . ' permission(s) granted to ' . $role['name']
        );

        return $this->respond($request, true, 'Permissions updated.', 'admin/roles');
    }
}
