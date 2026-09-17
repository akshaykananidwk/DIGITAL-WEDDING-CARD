<?php

declare(strict_types=1);

namespace App\Repositories;

final class AuditRepository extends BaseRepository
{
    protected string $table = 'audit_logs';
    protected bool $timestamps = false;

    public function paginateLogs(array $filters, int $page, int $perPage): array
    {
        $where = ['1=1'];
        $bindings = [];

        if (($filters['action'] ?? '') !== '') {
            $where[] = 'a.action = :action';
            $bindings['action'] = (string) $filters['action'];
        }
        if (($filters['user_id'] ?? '') !== '') {
            $where[] = 'a.user_id = :user';
            $bindings['user'] = (int) $filters['user_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(a.description LIKE :q OR a.actor_name LIKE :q2 OR a.action LIKE :q3)';
            $bindings['q'] = '%' . $filters['q'] . '%';
            $bindings['q2'] = '%' . $filters['q'] . '%';
            $bindings['q3'] = '%' . $filters['q'] . '%';
        }
        if (($filters['from'] ?? '') !== '') {
            $where[] = 'a.created_at >= :from';
            $bindings['from'] = $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $where[] = 'a.created_at <= :to';
            $bindings['to'] = $filters['to'] . ' 23:59:59';
        }

        return $this->db->paginate(
            'a.*',
            $this->qualified() . ' a WHERE ' . implode(' AND ', $where),
            $bindings,
            $page,
            $perPage,
            'a.created_at DESC, a.id DESC'
        );
    }

    /** @return array<int,string> distinct actions, for the filter dropdown */
    public function actions(): array
    {
        return array_map('strval', $this->db->column(
            'SELECT action FROM ' . $this->qualified() . ' GROUP BY action ORDER BY action ASC LIMIT 200'
        ));
    }

    public function purgeOlderThan(int $days): int
    {
        return $this->db->execute(
            'DELETE FROM ' . $this->qualified() . ' WHERE created_at < :cutoff',
            ['cutoff' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 10): array
    {
        return $this->db->select(
            'SELECT * FROM ' . $this->qualified() . ' ORDER BY id DESC LIMIT ' . max(1, min(100, $limit))
        );
    }
}
