<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Key/value content for an invitation (one row per template field). */
final class InvitationDataRepository extends BaseRepository
{
    protected string $table = 'invitation_data';
    protected array $jsonColumns = ['value_json'];

    /** @return array<string,mixed> field_key => value (json fields decoded) */
    public function forInvitation(int $invitationId): array
    {
        $rows = $this->db->select(
            'SELECT field_key, value, value_json FROM ' . $this->qualified()
            . ' WHERE invitation_id = :id',
            ['id' => $invitationId]
        );
        $out = [];
        foreach ($rows as $row) {
            $key = (string) $row['field_key'];
            if ($row['value_json'] !== null && $row['value_json'] !== '') {
                $decoded = json_decode((string) $row['value_json'], true);
                $out[$key] = is_array($decoded) ? $decoded : $row['value'];
                continue;
            }
            $out[$key] = $row['value'];
        }
        return $out;
    }

    /**
     * Write the whole content map in one transaction.
     *
     * @param array<string,mixed> $values
     */
    public function saveMany(int $invitationId, array $values): void
    {
        if ($values === []) {
            return;
        }
        $this->db->transaction(function () use ($invitationId, $values): void {
            $now = Database::now();
            foreach ($values as $key => $value) {
                $key = substr((string) $key, 0, 64);
                $isStructured = is_array($value);
                $this->db->upsert(
                    $this->table,
                    [
                        'invitation_id' => $invitationId,
                        'field_key'     => $key,
                        'value'         => $isStructured ? null : ($value === null ? null : (string) $value),
                        'value_json'    => $isStructured
                            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : null,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ],
                    ['invitation_id', 'field_key'],
                    ['value', 'value_json', 'updated_at']
                );
            }
        });
    }

    public function set(int $invitationId, string $key, mixed $value): void
    {
        $this->saveMany($invitationId, [$key => $value]);
    }

    public function get(int $invitationId, string $key, mixed $default = null): mixed
    {
        $row = $this->db->first(
            'SELECT value, value_json FROM ' . $this->qualified()
            . ' WHERE invitation_id = :id AND field_key = :key LIMIT 1',
            ['id' => $invitationId, 'key' => $key]
        );
        if ($row === null) {
            return $default;
        }
        if ($row['value_json'] !== null && $row['value_json'] !== '') {
            $decoded = json_decode((string) $row['value_json'], true);
            return is_array($decoded) ? $decoded : $row['value'];
        }
        return $row['value'] ?? $default;
    }

    public function deleteForInvitation(int $invitationId): int
    {
        return $this->db->delete($this->table, ['invitation_id' => $invitationId]);
    }

    /** Copy all content from one invitation to another (duplicate feature). */
    public function copy(int $fromId, int $toId): void
    {
        $this->saveMany($toId, $this->forInvitation($fromId));
    }
}
