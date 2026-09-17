<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * One-time login codes.
 *
 * Only hashes are stored, and the attempt counter lives on the row so a fresh
 * session cannot be used to get another six guesses.
 */
final class OtpRepository extends BaseRepository
{
    protected string $table = 'auth_otp_codes';

    /** Invalidate anything outstanding for this user and purpose. */
    public function invalidate(int $userId, string $purpose): int
    {
        return $this->db->execute(
            'UPDATE ' . $this->qualified() . ' SET consumed_at = :now, updated_at = :now2
             WHERE user_id = :user AND purpose = :purpose AND consumed_at IS NULL',
            ['now' => Database::now(), 'now2' => Database::now(), 'user' => $userId, 'purpose' => $purpose]
        );
    }

    /** The newest unconsumed, unexpired code for a user and purpose. */
    public function active(int $userId, string $purpose): ?array
    {
        return $this->db->first(
            'SELECT * FROM ' . $this->qualified() . '
             WHERE user_id = :user AND purpose = :purpose
               AND consumed_at IS NULL AND expires_at > :now
             ORDER BY id DESC LIMIT 1',
            ['user' => $userId, 'purpose' => $purpose, 'now' => Database::now()]
        );
    }

    public function consume(int $id): void
    {
        $this->db->update($this->table, [
            'consumed_at' => Database::now(),
            'updated_at'  => Database::now(),
        ], ['id' => $id]);
    }

    public function recordAttempt(int $id): int
    {
        $this->db->execute(
            'UPDATE ' . $this->qualified() . ' SET attempts = attempts + 1, updated_at = :now WHERE id = :id',
            ['now' => Database::now(), 'id' => $id]
        );
        return (int) $this->db->value(
            'SELECT attempts FROM ' . $this->qualified() . ' WHERE id = :id',
            ['id' => $id],
            0
        );
    }

    /** How many codes were issued to this user recently (a send throttle). */
    public function issuedSince(int $userId, string $purpose, int $seconds): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->qualified() . '
             WHERE user_id = :user AND purpose = :purpose AND created_at >= :since',
            [
                'user'    => $userId,
                'purpose' => $purpose,
                'since'   => date('Y-m-d H:i:s', time() - $seconds),
            ],
            0
        );
    }

    /** Housekeeping: drop codes that are long dead. */
    public function purgeExpired(int $days = 2): int
    {
        return $this->db->execute(
            'DELETE FROM ' . $this->qualified() . ' WHERE created_at < :cutoff',
            ['cutoff' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]
        );
    }
}
