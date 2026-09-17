<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class UserRepository extends BaseRepository
{
    protected string $table = 'users';
    protected bool $softDeletes = true;
    protected array $jsonColumns = ['preferences'];

    /** Users are always read together with their role (one query, no N+1). */
    private function selectWithRole(): string
    {
        return 'SELECT u.*, r.slug AS role_slug, r.name AS role_name, r.level AS role_level,
                       p.slug AS plan_slug, p.name AS plan_name
                FROM ' . $this->qualified() . ' u
                LEFT JOIN ' . $this->db->wrap($this->db->table('roles')) . ' r ON r.id = u.role_id
                LEFT JOIN ' . $this->db->wrap($this->db->table('plans')) . ' p ON p.id = u.plan_id';
    }

    public function find(int $id): ?array
    {
        $row = $this->db->first(
            $this->selectWithRole() . ' WHERE u.id = :id AND u.deleted_at IS NULL LIMIT 1',
            ['id' => $id]
        );
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByEmail(string $email): ?array
    {
        $row = $this->db->first(
            $this->selectWithRole() . ' WHERE u.email = :email AND u.deleted_at IS NULL LIMIT 1',
            ['email' => strtolower(trim($email))]
        );
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByVerifyToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $row = $this->db->first(
            $this->selectWithRole() . ' WHERE u.verify_token = :token AND u.deleted_at IS NULL LIMIT 1',
            ['token' => hash('sha256', $token)]
        );
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByRememberSelector(string $selector): ?array
    {
        $row = $this->db->first(
            $this->selectWithRole() . ' WHERE u.remember_selector = :selector AND u.deleted_at IS NULL LIMIT 1',
            ['selector' => $selector]
        );
        return $row === null ? null : $this->hydrate($row);
    }

    public function emailExists(string $email, ?int $ignoreId = null): bool
    {
        return $this->exists('email', strtolower(trim($email)), $ignoreId);
    }

    /**
     * Union of the primary role's permissions and any secondary roles held by
     * the user in user_roles. Cached per request by Auth.
     *
     * A single role's permissions are read through RoleRepository instead, so
     * the admin screens and the runtime check never drift apart.
     *
     * @return array<int,string>
     */
    public function permissionsForUser(int $userId): array
    {
        return array_map('strval', $this->db->column(
            'SELECT DISTINCT p.slug
             FROM ' . $this->db->wrap($this->db->table('permissions')) . ' p
             JOIN ' . $this->db->wrap($this->db->table('role_permissions')) . ' rp ON rp.permission_id = p.id
             WHERE rp.role_id = (SELECT role_id FROM ' . $this->qualified() . ' WHERE id = :id)
                OR rp.role_id IN (
                    SELECT role_id FROM ' . $this->db->wrap($this->db->table('user_roles')) . ' WHERE user_id = :id2
                )',
            ['id' => $userId, 'id2' => $userId]
        ));
    }

    public function touchLogin(int $userId): void
    {
        $this->db->update($this->table, [
            'last_login_at'      => Database::now(),
            'last_login_ip_hash' => \App\Core\Logger::clientIpHash(),
        ], ['id' => $userId]);
    }

    public function storeRememberToken(int $userId, string $selector, string $validatorHash, int $expires): void
    {
        $this->db->update($this->table, [
            'remember_selector'  => $selector,
            'remember_validator' => $validatorHash,
            'remember_expires'   => $expires,
        ], ['id' => $userId]);
    }

    public function clearRememberToken(int $userId): void
    {
        $this->db->update($this->table, [
            'remember_selector'  => null,
            'remember_validator' => null,
            'remember_expires'   => null,
        ], ['id' => $userId]);
    }

    public function markEmailVerified(int $userId): void
    {
        $this->db->update($this->table, [
            'email_verified_at' => Database::now(),
            'verify_token'      => null,
            'status'            => 'active',
        ], ['id' => $userId]);
    }

    /** Store only the hash of the verification token. */
    public function setVerifyToken(int $userId, string $plainToken): void
    {
        $this->db->update(
            $this->table,
            ['verify_token' => hash('sha256', $plainToken)],
            ['id' => $userId]
        );
    }

    public function incrementInvitationCount(int $userId, int $by = 1): void
    {
        $this->db->increment($this->table, 'invitation_count', ['id' => $userId], $by);
    }

    public function addStorageUsed(int $userId, int $bytes): void
    {
        // Clamp at zero: a deleted file must not drive the counter negative.
        $this->db->execute(
            'UPDATE ' . $this->qualified()
            . ' SET storage_used = CASE WHEN storage_used + :bytes < 0 THEN 0 ELSE storage_used + :bytes2 END'
            . ' WHERE id = :id',
            ['bytes' => $bytes, 'bytes2' => $bytes, 'id' => $userId]
        );
    }

    /** Admin listing with search and role filter. */
    public function search(array $filters, int $page, int $perPage): array
    {
        $where = ['u.deleted_at IS NULL'];
        $bindings = [];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(u.name LIKE :q OR u.email LIKE :q2 OR u.phone LIKE :q3)';
            $like = '%' . $filters['q'] . '%';
            $bindings['q'] = $like;
            $bindings['q2'] = $like;
            $bindings['q3'] = $like;
        }
        if (($filters['role'] ?? '') !== '') {
            $where[] = 'r.slug = :role';
            $bindings['role'] = $filters['role'];
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'u.status = :status';
            $bindings['status'] = $filters['status'];
        }

        $from = $this->qualified() . ' u
                LEFT JOIN ' . $this->db->wrap($this->db->table('roles')) . ' r ON r.id = u.role_id
                LEFT JOIN ' . $this->db->wrap($this->db->table('plans')) . ' pl ON pl.id = u.plan_id
                WHERE ' . implode(' AND ', $where);

        $result = $this->db->paginate(
            'u.*, r.slug AS role_slug, r.name AS role_name, pl.name AS plan_name',
            $from,
            $bindings,
            $page,
            $perPage,
            'u.created_at DESC'
        );
        $result['rows'] = $this->hydrateMany($result['rows']);
        return $result;
    }

    /** @return array{total:int,active:int,suspended:int,new_7d:int} */
    public function stats(): array
    {
        return [
            'total'     => (int) $this->db->value('SELECT COUNT(*) FROM ' . $this->qualified() . ' WHERE deleted_at IS NULL', [], 0),
            'active'    => (int) $this->db->value('SELECT COUNT(*) FROM ' . $this->qualified() . ' WHERE deleted_at IS NULL AND status = :s', ['s' => 'active'], 0),
            'suspended' => (int) $this->db->value('SELECT COUNT(*) FROM ' . $this->qualified() . ' WHERE deleted_at IS NULL AND status = :s', ['s' => 'suspended'], 0),
            'new_7d'    => (int) $this->db->value('SELECT COUNT(*) FROM ' . $this->qualified() . ' WHERE deleted_at IS NULL AND created_at >= :since', ['since' => date('Y-m-d H:i:s', strtotime('-7 days'))], 0),
        ];
    }

    /** Daily signups for the admin dashboard chart. */
    public function signupSeries(int $days = 30): array
    {
        $since = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        return $this->db->pairs(
            'SELECT ' . ($this->db->isSqlite() ? "substr(created_at,1,10)" : 'DATE(created_at)') . ' AS d, COUNT(*)
             FROM ' . $this->qualified() . '
             WHERE deleted_at IS NULL AND created_at >= :since
             GROUP BY d ORDER BY d ASC',
            ['since' => $since . ' 00:00:00']
        );
    }
}
