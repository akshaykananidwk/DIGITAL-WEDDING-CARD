<?php

declare(strict_types=1);

namespace App\Repositories;

final class BackupRepository extends BaseRepository
{
    protected string $table = 'backups';

    public function paginateBackups(int $page, int $perPage = 20): array
    {
        return $this->db->paginate(
            'b.*, u.name AS creator_name',
            $this->qualified() . ' b LEFT JOIN ' . $this->db->wrap($this->db->table('users')) . ' u ON u.id = b.created_by',
            [],
            $page,
            $perPage,
            'b.id DESC'
        );
    }

    public function latestCompleted(?string $type = null): ?array
    {
        $sql = 'SELECT * FROM ' . $this->qualified() . ' WHERE status = :status';
        $bindings = ['status' => 'completed'];
        if ($type !== null) {
            $sql .= ' AND type = :type';
            $bindings['type'] = $type;
        }
        return $this->db->first($sql . ' ORDER BY id DESC LIMIT 1', $bindings);
    }

    /** @return array<int,array<string,mixed>> oldest completed backups beyond the keep limit */
    public function beyondKeepLimit(int $keep): array
    {
        $ids = $this->db->column(
            'SELECT id FROM ' . $this->qualified() . '
             WHERE status = :status ORDER BY id DESC LIMIT 1000 OFFSET ' . max(0, $keep),
            ['status' => 'completed']
        );
        if ($ids === []) {
            return [];
        }
        return $this->db->select(
            'SELECT * FROM ' . $this->qualified() . ' WHERE id IN ('
            . implode(',', array_map('intval', $ids)) . ')'
        );
    }

    public function totalSize(): int
    {
        return (int) $this->db->value(
            'SELECT COALESCE(SUM(size),0) FROM ' . $this->qualified() . ' WHERE status = :s',
            ['s' => 'completed'],
            0
        );
    }
}
