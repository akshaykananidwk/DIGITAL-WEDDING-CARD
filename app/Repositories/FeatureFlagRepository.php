<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Cache;
use App\Core\Database;

final class FeatureFlagRepository extends BaseRepository
{
    protected string $table = 'feature_flags';
    protected array $jsonColumns = ['meta'];

    /** @return array<string,array<string,mixed>> keyed by flag_key */
    public function map(): array
    {
        return Cache::remember('flags:map', 600, function (): array {
            $out = [];
            foreach ($this->db->select('SELECT * FROM ' . $this->qualified() . ' ORDER BY name ASC') as $row) {
                $out[(string) $row['flag_key']] = $this->hydrate($row);
            }
            return $out;
        });
    }

    public function put(string $key, string $name, bool $enabled, int $rollout = 100, ?string $description = null): void
    {
        $this->db->upsert(
            $this->table,
            [
                'flag_key'    => $key,
                'name'        => $name,
                'description' => $description,
                'is_enabled'  => $enabled ? 1 : 0,
                'rollout'     => max(0, min(100, $rollout)),
                'created_at'  => Database::now(),
                'updated_at'  => Database::now(),
            ],
            ['flag_key'],
            ['name', 'description', 'is_enabled', 'rollout', 'updated_at']
        );
        $this->flush();
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        $this->db->update($this->table, ['is_enabled' => $enabled ? 1 : 0], ['flag_key' => $key]);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget('flags:map');
    }
}
