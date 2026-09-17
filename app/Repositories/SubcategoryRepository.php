<?php

declare(strict_types=1);

namespace App\Repositories;

final class SubcategoryRepository extends BaseRepository
{
    protected string $table = 'subcategories';
    protected bool $softDeletes = true;
    protected array $jsonColumns = ['theme_tags'];

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    /** @return array<int,array<string,mixed>> */
    public function forCategory(int $categoryId, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM ' . $this->qualified() . ' WHERE deleted_at IS NULL AND category_id = :cat';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';
        return $this->hydrateMany($this->db->select($sql, ['cat' => $categoryId]));
    }

    /** @return array<int,array<string,mixed>> with their parent category name */
    public function allWithCategory(): array
    {
        return $this->hydrateMany($this->db->select(
            'SELECT s.*, c.name AS category_name FROM ' . $this->qualified() . ' s
             JOIN ' . $this->db->wrap($this->db->table('categories')) . ' c ON c.id = s.category_id
             WHERE s.deleted_at IS NULL
             ORDER BY c.sort_order ASC, s.sort_order ASC, s.name ASC'
        ));
    }
}
