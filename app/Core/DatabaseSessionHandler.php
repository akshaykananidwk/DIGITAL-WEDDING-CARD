<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Stores sessions in the `sessions` table so the app stays stateless and can
 * run behind a load balancer. Payloads are opaque to the database.
 */
final class DatabaseSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::instance();
        if (!$this->db->tableExists('sessions')) {
            throw new \RuntimeException('sessions table is missing');
        }
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $row = $this->db->first('SELECT payload FROM sessions WHERE id = :id', ['id' => $id]);
        if ($row === null) {
            return '';
        }
        return (string) base64_decode((string) $row['payload'], true);
    }

    public function write(string $id, string $data): bool
    {
        $now = time();
        $userId = $_SESSION['user_id'] ?? null;

        $this->db->upsert('sessions', [
            'id'              => $id,
            'user_id'         => is_numeric($userId) ? (int) $userId : null,
            'ip_hash'         => Logger::clientIpHash(),
            'user_agent'      => mb_substr(Request::instance()->userAgent(), 0, 255),
            'payload'         => base64_encode($data),
            'last_activity'   => $now,
        ], ['id'], ['user_id', 'ip_hash', 'user_agent', 'payload', 'last_activity']);

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->db->execute('DELETE FROM sessions WHERE id = :id', ['id' => $id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $cutoff = time() - max(60, $max_lifetime);
        return $this->db->execute('DELETE FROM sessions WHERE last_activity < :cutoff', ['cutoff' => $cutoff]);
    }

    public function validateId(string $id): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9,\-]{22,128}$/', $id)
            && $this->db->first('SELECT id FROM sessions WHERE id = :id', ['id' => $id]) !== null;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $this->db->execute(
            'UPDATE sessions SET last_activity = :now WHERE id = :id',
            ['now' => time(), 'id' => $id]
        );
        return true;
    }
}
