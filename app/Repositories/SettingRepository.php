<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Crypto;
use App\Core\Database;

/** Persisted, admin-editable settings. Encrypted values stay sealed at rest. */
final class SettingRepository extends BaseRepository
{
    protected string $table = 'settings';

    /** @return array<int,array<string,mixed>> */
    public function allRows(): array
    {
        return $this->db->select('SELECT * FROM ' . $this->qualified() . ' ORDER BY group_name ASC, setting_key ASC');
    }

    /** @return array<string,array<string,mixed>> keyed by setting_key */
    public function forGroup(string $group): array
    {
        $rows = $this->db->select(
            'SELECT * FROM ' . $this->qualified() . ' WHERE group_name = :g ORDER BY setting_key ASC',
            ['g' => $group]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['setting_key']] = $row;
        }
        return $out;
    }

    public function findByKey(string $key): ?array
    {
        return $this->findBy('setting_key', $key);
    }

    public function put(
        string $key,
        mixed $value,
        string $type = 'string',
        string $group = 'general',
        ?int $userId = null
    ): void {
        $stored = match ($type) {
            'json'      => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            'boolean'   => $value ? '1' : '0',
            'integer'   => (string) (int) $value,
            'encrypted' => $value === '' || $value === null ? '' : Crypto::encrypt((string) $value),
            default     => $value === null ? null : (string) $value,
        };

        $this->db->upsert(
            $this->table,
            [
                'group_name'    => $group,
                'setting_key'   => $key,
                'setting_value' => $stored,
                'value_type'    => $type,
                'updated_by'    => $userId,
                'created_at'    => Database::now(),
                'updated_at'    => Database::now(),
            ],
            ['setting_key'],
            ['setting_value', 'value_type', 'group_name', 'updated_by', 'updated_at']
        );
    }

    /** Decode a stored row into its PHP value. */
    public function decode(array $row): mixed
    {
        $raw = $row['setting_value'] ?? null;
        return match ((string) $row['value_type']) {
            'json'      => is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?? []) : [],
            'boolean'   => (string) $raw === '1',
            'integer'   => (int) $raw,
            'encrypted' => Crypto::decrypt(is_string($raw) ? $raw : ''),
            default     => $raw,
        };
    }

    /** @return array<string,mixed> every setting, decoded */
    public function map(): array
    {
        $out = [];
        foreach ($this->allRows() as $row) {
            $out[(string) $row['setting_key']] = $this->decode($row);
        }
        return $out;
    }

    /** Public settings only - safe to expose to the browser. */
    public function publicMap(): array
    {
        $out = [];
        foreach ($this->allRows() as $row) {
            if ((int) $row['is_public'] === 1 && (string) $row['value_type'] !== 'encrypted') {
                $out[(string) $row['setting_key']] = $this->decode($row);
            }
        }
        return $out;
    }

    public function deleteByKey(string $key): int
    {
        return $this->db->delete($this->table, ['setting_key' => $key]);
    }
}
