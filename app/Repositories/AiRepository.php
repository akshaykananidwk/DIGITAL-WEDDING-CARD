<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Crypto;
use App\Core\Database;

/** Gemini configuration and usage log. */
final class AiRepository extends BaseRepository
{
    protected string $table = 'ai_settings';

    /** The single settings row, creating it on first use. */
    public function settings(): array
    {
        $row = $this->db->first('SELECT * FROM ' . $this->qualified() . ' ORDER BY id ASC LIMIT 1');
        if ($row === null) {
            $id = $this->create([
                'provider'      => 'gemini',
                'model'         => (string) \App\Core\Config::get('ai.model', 'gemini-2.0-flash'),
                'endpoint'      => (string) \App\Core\Config::get('ai.endpoint'),
                'temperature'   => 0.70,
                'top_p'         => 0.95,
                'max_tokens'    => 2048,
                'timeout'       => 30,
                'daily_limit'   => 100,
                'is_enabled'    => 0,
                'system_prompt' => null,
            ]);
            $row = $this->find($id) ?? [];
        }
        return $row;
    }

    public function apiKey(): string
    {
        $settings = $this->settings();
        return Crypto::decrypt((string) ($settings['api_key'] ?? ''));
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey() !== '';
    }

    public function saveSettings(array $data, ?int $userId = null): void
    {
        $current = $this->settings();
        // An empty key field means "leave the stored key alone".
        if (array_key_exists('api_key', $data)) {
            if ((string) $data['api_key'] === '') {
                unset($data['api_key']);
            } else {
                $data['api_key'] = Crypto::encrypt((string) $data['api_key']);
            }
        }
        $data['updated_by'] = $userId;
        $this->update((int) $current['id'], $data);
    }

    public function clearApiKey(?int $userId = null): void
    {
        $current = $this->settings();
        $this->update((int) $current['id'], ['api_key' => null, 'updated_by' => $userId]);
    }

    public function log(array $data): int
    {
        $data['created_at'] = Database::now();
        return $this->db->insert('ai_logs', $data);
    }

    public function usageToday(?int $userId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('ai_logs')) . '
                WHERE created_at >= :since AND status = :ok';
        $bindings = ['since' => date('Y-m-d') . ' 00:00:00', 'ok' => 'success'];
        if ($userId !== null) {
            $sql .= ' AND user_id = :user';
            $bindings['user'] = $userId;
        }
        return (int) $this->db->value($sql, $bindings, 0);
    }

    public function paginateLogs(array $filters, int $page, int $perPage): array
    {
        $where = ['1=1'];
        $bindings = [];
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'l.status = :status';
            $bindings['status'] = (string) $filters['status'];
        }
        if (($filters['action'] ?? '') !== '') {
            $where[] = 'l.action = :action';
            $bindings['action'] = (string) $filters['action'];
        }
        return $this->db->paginate(
            'l.*, u.name AS user_name',
            $this->db->wrap($this->db->table('ai_logs')) . ' l
             LEFT JOIN ' . $this->db->wrap($this->db->table('users')) . ' u ON u.id = l.user_id
             WHERE ' . implode(' AND ', $where),
            $bindings,
            $page,
            $perPage,
            'l.id DESC'
        );
    }

    /** @return array{total:int,success:int,errors:int,tokens:int} */
    public function stats(int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $table = $this->db->wrap($this->db->table('ai_logs'));
        $row = $this->db->first(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = :ok THEN 1 ELSE 0 END) AS success,
                    SUM(CASE WHEN status <> :ok2 THEN 1 ELSE 0 END) AS errors,
                    COALESCE(SUM(total_tokens),0) AS tokens
             FROM ' . $table . ' WHERE created_at >= :since',
            ['ok' => 'success', 'ok2' => 'success', 'since' => $since]
        ) ?? [];
        return [
            'total'   => (int) ($row['total'] ?? 0),
            'success' => (int) ($row['success'] ?? 0),
            'errors'  => (int) ($row['errors'] ?? 0),
            'tokens'  => (int) ($row['tokens'] ?? 0),
        ];
    }
}
