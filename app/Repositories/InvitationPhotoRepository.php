<?php

declare(strict_types=1);

namespace App\Repositories;

final class InvitationPhotoRepository extends BaseRepository
{
    protected string $table = 'invitation_photos';

    /** @return array<int,array<string,mixed>> */
    public function forInvitation(int $invitationId, ?string $role = null): array
    {
        $sql = 'SELECT * FROM ' . $this->qualified() . ' WHERE invitation_id = :id';
        $bindings = ['id' => $invitationId];
        if ($role !== null) {
            $sql .= ' AND role = :role';
            $bindings['role'] = $role;
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $this->db->select($sql, $bindings);
    }

    public function countForInvitation(int $invitationId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->qualified() . ' WHERE invitation_id = :id',
            ['id' => $invitationId],
            0
        );
    }

    public function findOwned(int $photoId, int $invitationId): ?array
    {
        return $this->db->first(
            'SELECT * FROM ' . $this->qualified() . ' WHERE id = :id AND invitation_id = :inv LIMIT 1',
            ['id' => $photoId, 'inv' => $invitationId]
        );
    }

    /** Persist a new drag-and-drop order. */
    public function reorder(int $invitationId, array $orderedIds): void
    {
        $this->db->transaction(function () use ($invitationId, $orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                $this->db->update(
                    $this->table,
                    ['sort_order' => $index * 10],
                    ['id' => (int) $id, 'invitation_id' => $invitationId]
                );
            }
        });
    }

    public function nextSortOrder(int $invitationId): int
    {
        return (int) $this->db->value(
            'SELECT COALESCE(MAX(sort_order), 0) + 10 FROM ' . $this->qualified() . ' WHERE invitation_id = :id',
            ['id' => $invitationId],
            10
        );
    }

    public function deleteForInvitation(int $invitationId): array
    {
        $paths = $this->forInvitation($invitationId);
        $this->db->delete($this->table, ['invitation_id' => $invitationId]);
        return $paths;
    }
}
