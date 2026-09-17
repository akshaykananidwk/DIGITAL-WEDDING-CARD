<?php

declare(strict_types=1);

namespace App\Repositories;

final class InvitationMusicRepository extends BaseRepository
{
    protected string $table = 'invitation_music';

    public function forInvitation(int $invitationId): ?array
    {
        return $this->db->first(
            'SELECT * FROM ' . $this->qualified() . ' WHERE invitation_id = :id ORDER BY id DESC LIMIT 1',
            ['id' => $invitationId]
        );
    }

    /** One track per invitation - replace whatever is there. */
    public function replace(int $invitationId, array $data): int
    {
        return $this->db->transaction(function () use ($invitationId, $data): int {
            $this->db->delete($this->table, ['invitation_id' => $invitationId]);
            $data['invitation_id'] = $invitationId;
            return $this->create($data);
        });
    }

    public function deleteForInvitation(int $invitationId): int
    {
        return $this->db->delete($this->table, ['invitation_id' => $invitationId]);
    }
}
