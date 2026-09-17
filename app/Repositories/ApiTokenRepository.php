<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Bearer tokens for the JSON API (mobile app ready). */
final class ApiTokenRepository extends BaseRepository
{
    protected string $table = 'api_tokens';
    protected array $jsonColumns = ['abilities'];

    /**
     * Create a token and return the plaintext exactly once.
     *
     * @return array{id:int,token:string}
     */
    public function issue(int $userId, string $name, array $abilities = ['*'], ?int $days = 365): array
    {
        $plain = 'inv_' . bin2hex(random_bytes(24));
        $id = $this->create([
            'user_id'    => $userId,
            'name'       => substr($name, 0, 80),
            'token_hash' => hash('sha256', $plain),
            'abilities'  => $abilities,
            'expires_at' => $days === null ? null : date('Y-m-d H:i:s', strtotime('+' . $days . ' days')),
        ]);
        return ['id' => $id, 'token' => $plain];
    }

    /** @return array<string,mixed>|null the owning user when the token is valid */
    public function resolveUser(string $plainToken): ?array
    {
        if ($plainToken === '' || strlen($plainToken) > 120) {
            return null;
        }
        $row = $this->db->first(
            'SELECT * FROM ' . $this->qualified() . ' WHERE token_hash = :hash LIMIT 1',
            ['hash' => hash('sha256', $plainToken)]
        );
        if ($row === null) {
            return null;
        }
        if ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        $this->db->update($this->table, ['last_used_at' => Database::now()], ['id' => (int) $row['id']]);

        return (new UserRepository())->find((int) $row['user_id']);
    }

    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId): array
    {
        return $this->hydrateMany($this->db->select(
            'SELECT id, name, abilities, last_used_at, expires_at, created_at FROM ' . $this->qualified()
            . ' WHERE user_id = :user ORDER BY created_at DESC',
            ['user' => $userId]
        ));
    }

    public function revoke(int $id, int $userId): int
    {
        return $this->db->delete($this->table, ['id' => $id, 'user_id' => $userId]);
    }
}
