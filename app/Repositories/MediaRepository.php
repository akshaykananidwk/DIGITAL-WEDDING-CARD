<?php

declare(strict_types=1);

namespace App\Repositories;

final class MediaRepository extends BaseRepository
{
    protected string $table = 'media';
    protected bool $softDeletes = true;

    public function paginateLibrary(array $filters, int $page, int $perPage): array
    {
        $where = ['deleted_at IS NULL'];
        $bindings = [];

        if (($filters['kind'] ?? '') !== '') {
            $where[] = 'kind = :kind';
            $bindings['kind'] = (string) $filters['kind'];
        }
        if (($filters['folder'] ?? '') !== '') {
            $where[] = 'folder = :folder';
            $bindings['folder'] = (string) $filters['folder'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(original_name LIKE :q OR title LIKE :q2 OR alt_text LIKE :q3)';
            $bindings['q'] = '%' . $filters['q'] . '%';
            $bindings['q2'] = '%' . $filters['q'] . '%';
            $bindings['q3'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['library_only'])) {
            $where[] = 'is_library = 1';
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :user';
            $bindings['user'] = (int) $filters['user_id'];
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

    public function findByHash(string $hash, ?int $userId = null): ?array
    {
        $sql = 'SELECT * FROM ' . $this->qualified() . ' WHERE content_hash = :hash AND deleted_at IS NULL';
        $bindings = ['hash' => $hash];
        if ($userId !== null) {
            $sql .= ' AND user_id = :user';
            $bindings['user'] = $userId;
        }
        return $this->db->first($sql . ' LIMIT 1', $bindings);
    }

    public function findByPath(string $path): ?array
    {
        return $this->findBy('path', $path);
    }

    /** @return array<int,string> distinct folders */
    public function folders(): array
    {
        return array_map('strval', $this->db->column(
            'SELECT folder FROM ' . $this->qualified() . '
             WHERE deleted_at IS NULL GROUP BY folder ORDER BY folder ASC'
        ));
    }

    /** @return array<int,array<string,mixed>> library tracks/images offered to users */
    public function library(string $kind, int $limit = 60): array
    {
        return $this->db->select(
            'SELECT * FROM ' . $this->qualified() . '
             WHERE deleted_at IS NULL AND is_library = 1 AND kind = :kind
             ORDER BY title ASC, original_name ASC LIMIT ' . max(1, min(200, $limit)),
            ['kind' => $kind]
        );
    }

    /**
     * Where is this asset used? Prevents deleting a file a template or an
     * invitation still needs without an explicit confirmation.
     *
     * @return array{templates:int,invitations:int,photos:int,music:int,total:int}
     */
    public function usage(array $media): array
    {
        $path = (string) $media['path'];
        $like = '%' . $path . '%';

        $templates = (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('templates')) . '
             WHERE deleted_at IS NULL AND (thumbnail = :path OR og_image = :path2
                   OR preview_images LIKE :like OR custom_html LIKE :like2 OR theme LIKE :like3)',
            ['path' => $path, 'path2' => $path, 'like' => $like, 'like2' => $like, 'like3' => $like],
            0
        );
        $assets = (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('template_assets')) . ' WHERE path = :path',
            ['path' => $path],
            0
        );
        $photos = (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('invitation_photos')) . ' WHERE path = :path',
            ['path' => $path],
            0
        );
        $music = (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('invitation_music')) . ' WHERE path = :path',
            ['path' => $path],
            0
        );
        $invitations = (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('invitations')) . '
             WHERE deleted_at IS NULL AND og_image = :path',
            ['path' => $path],
            0
        );

        return [
            'templates'   => $templates + $assets,
            'invitations' => $invitations,
            'photos'      => $photos,
            'music'       => $music,
            'total'       => $templates + $assets + $invitations + $photos + $music,
        ];
    }

    public function bumpUsage(int $mediaId, int $by = 1): void
    {
        $this->db->increment($this->table, 'usage_count', ['id' => $mediaId], $by);
    }

    /** @return array{count:int,bytes:int} */
    public function storageStats(?int $userId = null): array
    {
        $sql = 'SELECT COUNT(*) AS c, COALESCE(SUM(size),0) AS b FROM ' . $this->qualified()
            . ' WHERE deleted_at IS NULL';
        $bindings = [];
        if ($userId !== null) {
            $sql .= ' AND user_id = :user';
            $bindings['user'] = $userId;
        }
        $row = $this->db->first($sql, $bindings) ?? [];
        return ['count' => (int) ($row['c'] ?? 0), 'bytes' => (int) ($row['b'] ?? 0)];
    }
}
