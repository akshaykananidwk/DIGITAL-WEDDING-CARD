<?php

declare(strict_types=1);

namespace App\Repositories;

final class RoleRepository extends BaseRepository
{
    protected string $table = 'roles';

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    /** @return array<int,array<string,mixed>> */
    public function withUserCounts(): array
    {
        return $this->db->select(
            'SELECT r.*, (SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('users')) . ' u
                           WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS user_count
             FROM ' . $this->qualified() . ' r
             ORDER BY r.level DESC, r.name ASC'
        );
    }

    /** @return array<int,string> */
    public function permissionSlugs(int $roleId): array
    {
        return array_map('strval', $this->db->column(
            'SELECT p.slug FROM ' . $this->db->wrap($this->db->table('permissions')) . ' p
             JOIN ' . $this->db->wrap($this->db->table('role_permissions')) . ' rp ON rp.permission_id = p.id
             WHERE rp.role_id = :role ORDER BY p.slug',
            ['role' => $roleId]
        ));
    }

    /** Replace the permission set for a role in one transaction. */
    public function syncPermissions(int $roleId, array $permissionSlugs): void
    {
        $this->db->transaction(function () use ($roleId, $permissionSlugs): void {
            $this->db->execute(
                'DELETE FROM ' . $this->db->wrap($this->db->table('role_permissions')) . ' WHERE role_id = :role',
                ['role' => $roleId]
            );
            if ($permissionSlugs === []) {
                return;
            }
            $ids = $this->db->column(
                'SELECT id FROM ' . $this->db->wrap($this->db->table('permissions'))
                . ' WHERE slug IN (' . implode(',', array_map(
                    static fn ($i) => ':p' . $i,
                    array_keys($permissionSlugs)
                )) . ')',
                array_combine(
                    array_map(static fn ($i) => 'p' . $i, array_keys($permissionSlugs)),
                    array_values($permissionSlugs)
                )
            );
            $rows = [];
            foreach ($ids as $permissionId) {
                $rows[] = ['role_id' => $roleId, 'permission_id' => (int) $permissionId];
            }
            $this->db->insertMany('role_permissions', $rows);
        });
    }
}
