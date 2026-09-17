<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Per-invitation section visibility and ordering. */
final class InvitationSectionRepository extends BaseRepository
{
    protected string $table = 'invitation_sections';
    protected array $jsonColumns = ['config'];

    /** @return array<string,array<string,mixed>> keyed by section_key */
    public function forInvitation(int $invitationId): array
    {
        $rows = $this->hydrateMany($this->db->select(
            'SELECT * FROM ' . $this->qualified() . ' WHERE invitation_id = :id ORDER BY sort_order ASC',
            ['id' => $invitationId]
        ));
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['section_key']] = $row;
        }
        return $out;
    }

    /** @param array<string,array{is_visible?:bool,sort_order?:int,title?:string}> $sections */
    public function saveMany(int $invitationId, array $sections): void
    {
        $this->db->transaction(function () use ($invitationId, $sections): void {
            $now = Database::now();
            $order = 0;
            foreach ($sections as $key => $config) {
                $this->db->upsert(
                    $this->table,
                    [
                        'invitation_id' => $invitationId,
                        'section_key'   => substr((string) $key, 0, 64),
                        'title'         => isset($config['title']) ? substr((string) $config['title'], 0, 191) : null,
                        'is_visible'    => !empty($config['is_visible']) ? 1 : 0,
                        'sort_order'    => (int) ($config['sort_order'] ?? ($order += 10)),
                        'config'        => isset($config['config'])
                            ? json_encode($config['config'], JSON_UNESCAPED_UNICODE)
                            : null,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ],
                    ['invitation_id', 'section_key'],
                    ['title', 'is_visible', 'sort_order', 'config', 'updated_at']
                );
            }
        });
    }

    public function deleteForInvitation(int $invitationId): int
    {
        return $this->db->delete($this->table, ['invitation_id' => $invitationId]);
    }
}
