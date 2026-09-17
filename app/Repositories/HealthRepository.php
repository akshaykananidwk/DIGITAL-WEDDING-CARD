<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class HealthRepository extends BaseRepository
{
    protected string $table = 'system_health_logs';
    protected bool $timestamps = false;
    protected array $jsonColumns = ['results'];

    public function record(array $data): int
    {
        $data['checked_at'] = Database::now();
        if (isset($data['results']) && !is_string($data['results'])) {
            $data['results'] = json_encode($data['results'], JSON_UNESCAPED_UNICODE);
        }
        return $this->db->insert($this->table, $data);
    }

    public function latest(): ?array
    {
        $row = $this->db->first('SELECT * FROM ' . $this->qualified() . ' ORDER BY id DESC LIMIT 1');
        return $row === null ? null : $this->hydrate($row);
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 20): array
    {
        return $this->hydrateMany($this->db->select(
            'SELECT * FROM ' . $this->qualified() . ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit))
        ));
    }

    public function purgeOlderThan(int $days): int
    {
        return $this->db->execute(
            'DELETE FROM ' . $this->qualified() . ' WHERE checked_at < :cutoff',
            ['cutoff' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]
        );
    }
}
