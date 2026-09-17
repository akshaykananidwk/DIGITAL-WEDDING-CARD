<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class CronRepository extends BaseRepository
{
    protected string $table = 'cron_runs';
    protected bool $timestamps = false;

    public function record(string $task, string $status, string $output = '', int $durationMs = 0): int
    {
        return $this->db->insert($this->table, [
            'task'        => substr($task, 0, 60),
            'status'      => $status,
            'output'      => mb_substr($output, 0, 4000),
            'duration_ms' => $durationMs,
            'ran_at'      => Database::now(),
        ]);
    }

    public function lastRun(?string $task = null): ?array
    {
        $sql = 'SELECT * FROM ' . $this->qualified();
        $bindings = [];
        if ($task !== null) {
            $sql .= ' WHERE task = :task';
            $bindings['task'] = $task;
        }
        return $this->db->first($sql . ' ORDER BY id DESC LIMIT 1', $bindings);
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 30): array
    {
        return $this->db->select(
            'SELECT * FROM ' . $this->qualified() . ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit))
        );
    }

    /** @return array<string,array<string,mixed>> latest run per task */
    public function latestPerTask(): array
    {
        $out = [];
        foreach ($this->recent(100) as $row) {
            $task = (string) $row['task'];
            if (!isset($out[$task])) {
                $out[$task] = $row;
            }
        }
        return $out;
    }

    public function purgeOlderThan(int $days): int
    {
        return $this->db->execute(
            'DELETE FROM ' . $this->qualified() . ' WHERE ran_at < :cutoff',
            ['cutoff' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]
        );
    }
}
