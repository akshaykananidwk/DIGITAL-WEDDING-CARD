<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class NotificationRepository extends BaseRepository
{
    protected string $table = 'notifications';
    protected bool $timestamps = false;

    public function push(?int $userId, string $title, string $body = '', string $type = 'info', ?string $url = null, ?string $icon = null): int
    {
        return $this->db->insert($this->table, [
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => substr($title, 0, 191),
            'body'       => substr($body, 0, 500),
            'url'        => $url === null ? null : substr($url, 0, 255),
            'icon'       => $icon,
            'is_read'    => 0,
            'created_at' => Database::now(),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId, int $limit = 15): array
    {
        return $this->db->select(
            'SELECT * FROM ' . $this->qualified() . '
             WHERE user_id = :user OR user_id IS NULL
             ORDER BY is_read ASC, id DESC LIMIT ' . max(1, min(100, $limit)),
            ['user' => $userId]
        );
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->qualified() . '
             WHERE (user_id = :user OR user_id IS NULL) AND is_read = 0',
            ['user' => $userId],
            0
        );
    }

    public function markRead(int $id, int $userId): int
    {
        return $this->db->execute(
            'UPDATE ' . $this->qualified() . ' SET is_read = 1, read_at = :now
             WHERE id = :id AND (user_id = :user OR user_id IS NULL)',
            ['now' => Database::now(), 'id' => $id, 'user' => $userId]
        );
    }

    public function markAllRead(int $userId): int
    {
        return $this->db->execute(
            'UPDATE ' . $this->qualified() . ' SET is_read = 1, read_at = :now
             WHERE (user_id = :user OR user_id IS NULL) AND is_read = 0',
            ['now' => Database::now(), 'user' => $userId]
        );
    }

    /** Broadcast to every administrator (update available, failed backup…). */
    public function notifyAdmins(string $title, string $body = '', string $type = 'info', ?string $url = null): int
    {
        $ids = $this->db->column(
            'SELECT u.id FROM ' . $this->db->wrap($this->db->table('users')) . ' u
             JOIN ' . $this->db->wrap($this->db->table('roles')) . ' r ON r.id = u.role_id
             WHERE u.deleted_at IS NULL AND u.status = :active AND r.slug IN (:a, :b)',
            ['active' => 'active', 'a' => 'super-admin', 'b' => 'admin']
        );
        $count = 0;
        foreach ($ids as $id) {
            $this->push((int) $id, $title, $body, $type, $url);
            $count++;
        }
        return $count;
    }

    public function purgeOlderThan(int $days): int
    {
        return $this->db->execute(
            'DELETE FROM ' . $this->qualified() . ' WHERE is_read = 1 AND created_at < :cutoff',
            ['cutoff' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]
        );
    }
}
