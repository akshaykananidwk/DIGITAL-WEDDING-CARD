<?php

declare(strict_types=1);

namespace App\Repositories;

final class PageRepository extends BaseRepository
{
    protected string $table = 'pages';
    protected bool $softDeletes = true;

    public function findPublished(string $slug): ?array
    {
        return $this->db->first(
            'SELECT * FROM ' . $this->qualified()
            . ' WHERE slug = :slug AND status = :status AND deleted_at IS NULL LIMIT 1',
            ['slug' => $slug, 'status' => 'published']
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function footerLinks(): array
    {
        return $this->db->select(
            'SELECT title, slug FROM ' . $this->qualified() . '
             WHERE status = :status AND deleted_at IS NULL AND show_in_footer = 1
             ORDER BY sort_order ASC, title ASC',
            ['status' => 'published']
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function headerLinks(): array
    {
        return $this->db->select(
            'SELECT title, slug FROM ' . $this->qualified() . '
             WHERE status = :status AND deleted_at IS NULL AND show_in_header = 1
             ORDER BY sort_order ASC, title ASC',
            ['status' => 'published']
        );
    }

    /** @return array<int,array<string,mixed>> for the sitemap */
    public function published(): array
    {
        return $this->db->select(
            'SELECT slug, updated_at FROM ' . $this->qualified() . '
             WHERE status = :status AND deleted_at IS NULL ORDER BY sort_order ASC',
            ['status' => 'published']
        );
    }
}
