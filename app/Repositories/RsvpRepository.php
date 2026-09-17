<?php

declare(strict_types=1);

namespace App\Repositories;

final class RsvpRepository extends BaseRepository
{
    protected string $table = 'rsvp';

    public function paginateForInvitation(int $invitationId, array $filters, int $page, int $perPage): array
    {
        $where = ['invitation_id = :inv'];
        $bindings = ['inv' => $invitationId];
        if (($filters['response'] ?? '') !== '') {
            $where[] = 'response = :response';
            $bindings['response'] = (string) $filters['response'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(name LIKE :q OR phone LIKE :q2)';
            $bindings['q'] = '%' . $filters['q'] . '%';
            $bindings['q2'] = '%' . $filters['q'] . '%';
        }
        return $this->db->paginate(
            '*',
            $this->qualified() . ' WHERE ' . implode(' AND ', $where),
            $bindings,
            $page,
            $perPage,
            'created_at DESC'
        );
    }

    /** @return array{yes:int,maybe:int,no:int,total:int,guests:int} */
    public function summary(int $invitationId): array
    {
        $row = $this->db->first(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN response = :yes THEN 1 ELSE 0 END) AS yes_count,
                    SUM(CASE WHEN response = :maybe THEN 1 ELSE 0 END) AS maybe_count,
                    SUM(CASE WHEN response = :no THEN 1 ELSE 0 END) AS no_count,
                    COALESCE(SUM(CASE WHEN response = :yes2 THEN guests ELSE 0 END), 0) AS guest_count
             FROM ' . $this->qualified() . ' WHERE invitation_id = :inv',
            ['inv' => $invitationId, 'yes' => 'yes', 'maybe' => 'maybe', 'no' => 'no', 'yes2' => 'yes']
        ) ?? [];

        return [
            'total'  => (int) ($row['total'] ?? 0),
            'yes'    => (int) ($row['yes_count'] ?? 0),
            'maybe'  => (int) ($row['maybe_count'] ?? 0),
            'no'     => (int) ($row['no_count'] ?? 0),
            'guests' => (int) ($row['guest_count'] ?? 0),
        ];
    }

    /** Stops the same visitor spamming the form, without blocking families. */
    public function recentFromVisitor(int $invitationId, string $visitorHash, int $seconds = 120): bool
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->qualified() . '
             WHERE invitation_id = :inv AND visitor_hash = :hash AND created_at >= :since',
            [
                'inv'   => $invitationId,
                'hash'  => $visitorHash,
                'since' => date('Y-m-d H:i:s', time() - $seconds),
            ],
            0
        ) > 0;
    }

    /** Existing response from the same visitor, so they can change their mind. */
    public function findByVisitor(int $invitationId, string $visitorHash): ?array
    {
        return $this->db->first(
            'SELECT * FROM ' . $this->qualified() . '
             WHERE invitation_id = :inv AND visitor_hash = :hash ORDER BY id DESC LIMIT 1',
            ['inv' => $invitationId, 'hash' => $visitorHash]
        );
    }

    public function markAllRead(int $invitationId): int
    {
        return $this->db->update($this->table, ['is_read' => 1], ['invitation_id' => $invitationId]);
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->qualified() . ' r
             JOIN ' . $this->db->wrap($this->db->table('invitations')) . ' i ON i.id = r.invitation_id
             WHERE i.user_id = :user AND i.deleted_at IS NULL AND r.is_read = 0',
            ['user' => $userId],
            0
        );
    }

    /** @return array<int,array<string,mixed>> for CSV export */
    public function allForInvitation(int $invitationId): array
    {
        return $this->db->select(
            'SELECT name, phone, email, response, guests, message, created_at
             FROM ' . $this->qualified() . ' WHERE invitation_id = :inv ORDER BY created_at ASC',
            ['inv' => $invitationId]
        );
    }
}
