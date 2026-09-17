<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** History of every update attempt, successful or not. */
final class UpdateRepository extends BaseRepository
{
    protected string $table = 'update_logs';
    protected array $jsonColumns = ['changed_files', 'migration_result', 'health_result', 'steps_log'];

    public function start(array $data): int
    {
        $data['status'] = $data['status'] ?? 'checking';
        $data['started_at'] = Database::now();
        $data['steps_log'] = [];
        return $this->create($data);
    }

    /** Move the run to a new step, appending to its timeline. */
    public function step(int $id, string $status, string $step, array $extra = []): void
    {
        $current = $this->find($id);
        $log = is_array($current['steps_log'] ?? null) ? $current['steps_log'] : [];
        $log[] = [
            'step'   => $step,
            'status' => $status,
            'at'     => date('c'),
        ];
        $this->update($id, array_merge([
            'status'    => $status,
            'step'      => $step,
            'steps_log' => $log,
        ], $extra));
    }

    public function finish(int $id, string $status, array $extra = []): void
    {
        $current = $this->find($id);
        $startedAt = isset($current['started_at']) ? strtotime((string) $current['started_at']) : time();
        $this->update($id, array_merge([
            'status'      => $status,
            'finished_at' => Database::now(),
            'duration_ms' => max(0, (time() - (int) $startedAt) * 1000),
        ], $extra));
    }

    public function paginateHistory(int $page, int $perPage = 20): array
    {
        $result = $this->db->paginate(
            'l.*, u.name AS admin_name',
            $this->qualified() . ' l LEFT JOIN ' . $this->db->wrap($this->db->table('users')) . ' u ON u.id = l.initiated_by',
            [],
            $page,
            $perPage,
            'l.id DESC'
        );
        $result['rows'] = $this->hydrateMany($result['rows']);
        return $result;
    }

    public function latest(): ?array
    {
        $row = $this->db->first('SELECT * FROM ' . $this->qualified() . ' ORDER BY id DESC LIMIT 1');
        return $row === null ? null : $this->hydrate($row);
    }

    public function lastSuccessful(): ?array
    {
        $row = $this->db->first(
            'SELECT * FROM ' . $this->qualified() . ' WHERE status = :s ORDER BY id DESC LIMIT 1',
            ['s' => 'success']
        );
        return $row === null ? null : $this->hydrate($row);
    }

    /** An update that is still mid-flight (used to detect a crashed run). */
    public function inProgress(): ?array
    {
        $row = $this->db->first(
            'SELECT * FROM ' . $this->qualified() . "
             WHERE finished_at IS NULL
               AND status NOT IN ('success', 'failed', 'rolled_back')
             ORDER BY id DESC LIMIT 1"
        );
        return $row === null ? null : $this->hydrate($row);
    }
}
