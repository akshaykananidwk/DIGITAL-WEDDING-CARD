<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class InvitationRepository extends BaseRepository
{
    protected string $table = 'invitations';
    protected bool $softDeletes = true;
    protected array $jsonColumns = ['theme_overrides', 'settings'];

    /** Invitation + the template metadata needed to render it. */
    public function findForRender(string $slug): ?array
    {
        $row = $this->db->first(
            'SELECT i.*, t.layout_key, t.type AS template_type, t.theme AS template_theme,
                    t.custom_html, t.custom_css, t.custom_js, t.page_count,
                    t.color_primary, t.color_secondary, t.color_background,
                    t.font_heading, t.font_body, t.name AS template_name, t.code AS template_code,
                    t.supports_music, t.supports_gallery, t.supports_countdown,
                    t.supports_rsvp, t.supports_map, t.canvas_width, t.canvas_height,
                    u.name AS owner_name
             FROM ' . $this->qualified() . ' i
             JOIN ' . $this->db->wrap($this->db->table('templates')) . ' t ON t.id = i.template_id
             JOIN ' . $this->db->wrap($this->db->table('users')) . ' u ON u.id = i.user_id
             WHERE (i.slug = :slug OR i.short_code = :code) AND i.deleted_at IS NULL
             LIMIT 1',
            ['slug' => $slug, 'code' => strtoupper($slug)]
        );
        if ($row === null) {
            return null;
        }
        $row = $this->hydrate($row);
        $row['template_theme'] = $this->decode($row['template_theme'] ?? null);
        return $row;
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    public function findByShortCode(string $code): ?array
    {
        return $this->findBy('short_code', strtoupper($code));
    }

    /** Owner-scoped fetch: the single guard against IDOR on invitations. */
    public function findOwned(int $id, int $userId): ?array
    {
        $row = $this->db->first(
            'SELECT * FROM ' . $this->qualified()
            . ' WHERE id = :id AND user_id = :user AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'user' => $userId]
        );
        return $row === null ? null : $this->hydrate($row);
    }

    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId, int $limit = 0, ?string $status = null): array
    {
        $sql = 'SELECT i.*, t.name AS template_name, t.thumbnail AS template_thumbnail,
                       t.layout_key, t.color_primary
                FROM ' . $this->qualified() . ' i
                JOIN ' . $this->db->wrap($this->db->table('templates')) . ' t ON t.id = i.template_id
                WHERE i.user_id = :user AND i.deleted_at IS NULL';
        $bindings = ['user' => $userId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND i.status = :status';
            $bindings['status'] = $status;
        }
        $sql .= ' ORDER BY i.updated_at DESC, i.id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        return $this->hydrateMany($this->db->select($sql, $bindings));
    }

    public function paginateForUser(int $userId, array $filters, int $page, int $perPage): array
    {
        $where = ['i.user_id = :user', 'i.deleted_at IS NULL'];
        $bindings = ['user' => $userId];

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'i.status = :status';
            $bindings['status'] = (string) $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(i.title LIKE :q OR i.slug LIKE :q2)';
            $bindings['q'] = '%' . $filters['q'] . '%';
            $bindings['q2'] = '%' . $filters['q'] . '%';
        }

        $from = $this->qualified() . ' i
                JOIN ' . $this->db->wrap($this->db->table('templates')) . ' t ON t.id = i.template_id
                WHERE ' . implode(' AND ', $where);

        $result = $this->db->paginate(
            'i.*, t.name AS template_name, t.thumbnail AS template_thumbnail, t.layout_key',
            $from,
            $bindings,
            $page,
            $perPage,
            'i.updated_at DESC'
        );
        $result['rows'] = $this->hydrateMany($result['rows']);
        return $result;
    }

    /** Admin listing across every user. */
    public function paginateAll(array $filters, int $page, int $perPage): array
    {
        $where = ['i.deleted_at IS NULL'];
        $bindings = [];

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'i.status = :status';
            $bindings['status'] = (string) $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(i.title LIKE :q OR i.slug LIKE :q2 OR u.email LIKE :q3)';
            $bindings['q'] = '%' . $filters['q'] . '%';
            $bindings['q2'] = '%' . $filters['q'] . '%';
            $bindings['q3'] = '%' . $filters['q'] . '%';
        }

        $from = $this->qualified() . ' i
                JOIN ' . $this->db->wrap($this->db->table('users')) . ' u ON u.id = i.user_id
                JOIN ' . $this->db->wrap($this->db->table('templates')) . ' t ON t.id = i.template_id
                WHERE ' . implode(' AND ', $where);

        $result = $this->db->paginate(
            'i.*, u.name AS owner_name, u.email AS owner_email, t.name AS template_name',
            $from,
            $bindings,
            $page,
            $perPage,
            'i.created_at DESC'
        );
        $result['rows'] = $this->hydrateMany($result['rows']);
        return $result;
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return $this->exists('slug', $slug, $ignoreId);
    }

    public function shortCodeExists(string $code): bool
    {
        return $this->exists('short_code', $code);
    }

    /** @return array<string,mixed> aggregate counters for the user dashboard */
    public function dashboardStats(int $userId): array
    {
        $row = $this->db->first(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = :published THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status = :draft THEN 1 ELSE 0 END) AS drafts,
                    COALESCE(SUM(view_count), 0) AS views,
                    COALESCE(SUM(unique_view_count), 0) AS unique_views,
                    COALESCE(SUM(share_count), 0) AS shares,
                    COALESCE(SUM(download_count), 0) AS downloads,
                    COALESCE(SUM(rsvp_count), 0) AS rsvps
             FROM ' . $this->qualified() . '
             WHERE user_id = :user AND deleted_at IS NULL',
            ['user' => $userId, 'published' => 'published', 'draft' => 'draft']
        ) ?? [];

        return [
            'total'        => (int) ($row['total'] ?? 0),
            'active'       => (int) ($row['active'] ?? 0),
            'drafts'       => (int) ($row['drafts'] ?? 0),
            'views'        => (int) ($row['views'] ?? 0),
            'unique_views' => (int) ($row['unique_views'] ?? 0),
            'shares'       => (int) ($row['shares'] ?? 0),
            'downloads'    => (int) ($row['downloads'] ?? 0),
            'rsvps'        => (int) ($row['rsvps'] ?? 0),
        ];
    }

    /** @return array{total:int,published:int,drafts:int,views:int} */
    public function stats(): array
    {
        $base = 'SELECT COUNT(*) FROM ' . $this->qualified() . ' WHERE deleted_at IS NULL';
        return [
            'total'     => (int) $this->db->value($base, [], 0),
            'published' => (int) $this->db->value($base . ' AND status = :s', ['s' => 'published'], 0),
            'drafts'    => (int) $this->db->value($base . ' AND status = :s', ['s' => 'draft'], 0),
            'views'     => (int) $this->db->value(
                'SELECT COALESCE(SUM(view_count),0) FROM ' . $this->qualified() . ' WHERE deleted_at IS NULL',
                [],
                0
            ),
        ];
    }

    public function publish(int $id): void
    {
        $this->db->update($this->table, [
            'status'       => 'published',
            'published_at' => Database::now(),
            'updated_at'   => Database::now(),
        ], ['id' => $id]);
    }

    public function unpublish(int $id): void
    {
        $this->db->update($this->table, [
            'status'     => 'unpublished',
            'updated_at' => Database::now(),
        ], ['id' => $id]);
    }

    public function bumpCounter(int $id, string $column, int $by = 1): void
    {
        $allowed = ['view_count', 'unique_view_count', 'share_count', 'download_count', 'qr_scan_count', 'rsvp_count'];
        if (!in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException('Unknown counter: ' . $column);
        }
        $this->db->increment($this->table, $column, ['id' => $id], $by);
    }

    public function touchViewed(int $id): void
    {
        $this->db->update($this->table, ['last_viewed_at' => Database::now()], ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> invitations whose event has passed */
    public function expiredCandidates(int $graceDays = 30): array
    {
        return $this->db->select(
            'SELECT id, user_id, title, event_at FROM ' . $this->qualified() . '
             WHERE deleted_at IS NULL AND status = :status
               AND event_at IS NOT NULL AND event_at < :cutoff',
            [
                'status' => 'published',
                'cutoff' => date('Y-m-d H:i:s', strtotime('-' . $graceDays . ' days')),
            ]
        );
    }

    /** @return array<int,array<string,mixed>> published invitations for the sitemap */
    public function publicForSitemap(int $limit = 5000): array
    {
        return $this->db->select(
            'SELECT slug, updated_at FROM ' . $this->qualified() . '
             WHERE deleted_at IS NULL AND status = :status
             ORDER BY published_at DESC LIMIT ' . max(1, min(50000, $limit)),
            ['status' => 'published']
        );
    }
}
