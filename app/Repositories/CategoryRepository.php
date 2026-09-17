<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Cache;

final class CategoryRepository extends BaseRepository
{
    protected string $table = 'categories';
    protected bool $softDeletes = true;

    private const CACHE_KEY = 'categories:tree';

    /** @return array<int,array<string,mixed>> active categories, each with `subcategories` */
    public function tree(bool $activeOnly = true): array
    {
        $key = self::CACHE_KEY . ($activeOnly ? ':active' : ':all');

        return Cache::remember($key, 900, function () use ($activeOnly): array {
            $categoryWhere = 'deleted_at IS NULL' . ($activeOnly ? ' AND is_active = 1' : '');
            $categories = $this->db->select(
                'SELECT * FROM ' . $this->qualified() . ' WHERE ' . $categoryWhere
                . ' ORDER BY sort_order ASC, name ASC'
            );

            $subWhere = 'deleted_at IS NULL' . ($activeOnly ? ' AND is_active = 1' : '');
            $subs = $this->db->select(
                'SELECT * FROM ' . $this->db->wrap($this->db->table('subcategories'))
                . ' WHERE ' . $subWhere . ' ORDER BY sort_order ASC, name ASC'
            );

            $grouped = [];
            foreach ($subs as $sub) {
                $sub['theme_tags'] = $this->decodeJson($sub['theme_tags'] ?? null);
                $grouped[(int) $sub['category_id']][] = $sub;
            }
            foreach ($categories as &$category) {
                $category['subcategories'] = $grouped[(int) $category['id']] ?? [];
            }
            return $categories;
        });
    }

    private function decodeJson(mixed $value): array
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

    /** Recalculate the cached template counts on both taxonomy levels. */
    public function refreshCounts(): void
    {
        $templates = $this->db->wrap($this->db->table('templates'));
        $this->db->execute(
            'UPDATE ' . $this->qualified() . ' c SET template_count = (
                SELECT COUNT(*) FROM ' . $templates . ' t
                WHERE t.category_id = c.id AND t.is_active = 1 AND t.deleted_at IS NULL
             )'
        );
        $this->db->execute(
            'UPDATE ' . $this->db->wrap($this->db->table('subcategories')) . ' s SET template_count = (
                SELECT COUNT(*) FROM ' . $templates . ' t
                WHERE t.subcategory_id = s.id AND t.is_active = 1 AND t.deleted_at IS NULL
             )'
        );
        $this->flushCache();
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY . ':active');
        Cache::forget(self::CACHE_KEY . ':all');
    }

    /** Flat select options for forms. */
    public function options(): array
    {
        $out = [];
        foreach ($this->tree(false) as $category) {
            $out[(int) $category['id']] = (string) $category['name'];
        }
        return $out;
    }
}
