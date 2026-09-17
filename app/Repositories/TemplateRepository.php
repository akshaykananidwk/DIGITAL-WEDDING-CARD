<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Cache;
use App\Core\Database;

/**
 * Template queries.
 *
 * Everything is paginated and index-backed: the gallery never selects the
 * heavy columns (custom_html/css/js, demo_data) and never loads all rows.
 */
final class TemplateRepository extends BaseRepository
{
    protected string $table = 'templates';
    protected bool $softDeletes = true;
    protected array $jsonColumns = ['theme', 'preview_images', 'demo_data', 'tags'];

    /** Columns needed to render a gallery card - deliberately not SELECT *. */
    public const CARD_COLUMNS = 't.id, t.code, t.name, t.slug, t.type, t.layout_key, t.language,
        t.thumbnail, t.color_primary, t.color_secondary, t.color_background,
        t.font_heading, t.is_premium, t.is_featured, t.has_animation, t.page_count,
        t.use_count, t.view_count, t.rating, t.category_id, t.subcategory_id, t.tags,
        t.orientation, t.is_active, t.created_at';

    public function findBySlug(string $slug, bool $activeOnly = true): ?array
    {
        $sql = 'SELECT * FROM ' . $this->qualified() . ' WHERE slug = :slug AND deleted_at IS NULL';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $row = $this->db->first($sql . ' LIMIT 1', ['slug' => $slug]);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByCode(string $code): ?array
    {
        return $this->findBy('code', $code);
    }

    /** Cached full template definition (fields + components) for rendering. */
    public function definition(int $templateId): ?array
    {
        return Cache::remember('template:def:' . $templateId, 1800, function () use ($templateId): ?array {
            $template = $this->find($templateId);
            if ($template === null) {
                return null;
            }
            $template['fields'] = (new TemplateFieldRepository())->forTemplate($templateId);
            $template['components'] = (new TemplateComponentRepository())->forTemplate($templateId);
            return $template;
        });
    }

    public function flushDefinition(int $templateId): void
    {
        Cache::forget('template:def:' . $templateId);
    }

    /**
     * Gallery/search query.
     *
     * @param array{
     *   q?:string, category?:int, subcategory?:int, type?:string, language?:string,
     *   premium?:string, animated?:string, color?:string, tag?:string, sort?:string,
     *   featured?:bool, active?:bool
     * } $filters
     */
    public function search(array $filters, int $page = 1, int $perPage = 24): array
    {
        $where = ['t.deleted_at IS NULL'];
        $bindings = [];

        if (($filters['active'] ?? true) !== false) {
            $where[] = 't.is_active = 1';
        }
        if (!empty($filters['category'])) {
            $where[] = 't.category_id = :category';
            $bindings['category'] = (int) $filters['category'];
        }
        if (!empty($filters['subcategory'])) {
            $where[] = 't.subcategory_id = :subcategory';
            $bindings['subcategory'] = (int) $filters['subcategory'];
        }
        if (!empty($filters['type'])) {
            $where[] = 't.type = :type';
            $bindings['type'] = (string) $filters['type'];
        }
        if (!empty($filters['language']) && $filters['language'] !== 'all') {
            $where[] = '(t.language = :language OR t.language = :multi)';
            $bindings['language'] = (string) $filters['language'];
            $bindings['multi'] = 'multi';
        }
        if (isset($filters['premium']) && $filters['premium'] !== '') {
            $where[] = 't.is_premium = :premium';
            $bindings['premium'] = $filters['premium'] === '1' || $filters['premium'] === 1 ? 1 : 0;
        }
        if (!empty($filters['animated'])) {
            $where[] = 't.has_animation = 1';
        }
        if (!empty($filters['featured'])) {
            $where[] = 't.is_featured = 1';
        }
        if (!empty($filters['color'])) {
            $where[] = 't.color_primary = :color';
            $bindings['color'] = (string) $filters['color'];
        }
        if (!empty($filters['tag'])) {
            // tags is a JSON array of strings; LIKE on the encoded text is both
            // portable and index-assisted enough at this scale.
            $where[] = 't.tags LIKE :tag';
            $bindings['tag'] = '%"' . str_replace(['%', '_'], '', (string) $filters['tag']) . '"%';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            // MySQL FULLTEXT when available, LIKE elsewhere. Both bound.
            if ($this->db->isMysql() && mb_strlen($q) >= 3 && !str_contains($q, '"')) {
                $where[] = '(MATCH(t.name, t.search_keywords) AGAINST (:q IN BOOLEAN MODE)
                             OR t.name LIKE :qlike OR t.code LIKE :qcode)';
                $bindings['q'] = $this->booleanTerms($q);
                $bindings['qlike'] = '%' . $q . '%';
                $bindings['qcode'] = $q . '%';
            } else {
                $where[] = '(t.name LIKE :qlike OR t.search_keywords LIKE :qkw OR t.code LIKE :qcode)';
                $bindings['qlike'] = '%' . $q . '%';
                $bindings['qkw'] = '%' . $q . '%';
                $bindings['qcode'] = $q . '%';
            }
        }

        $order = match ((string) ($filters['sort'] ?? 'popular')) {
            'latest'   => 't.created_at DESC, t.id DESC',
            'name'     => 't.name ASC',
            'rating'   => 't.rating DESC, t.use_count DESC',
            'featured' => 't.is_featured DESC, t.sort_order ASC, t.use_count DESC',
            default    => 't.is_featured DESC, t.use_count DESC, t.view_count DESC, t.id DESC',
        };

        $from = $this->qualified() . ' t WHERE ' . implode(' AND ', $where);

        $result = $this->db->paginate(self::CARD_COLUMNS, $from, $bindings, $page, $perPage, $order);
        $result['rows'] = $this->hydrateMany($result['rows']);
        return $result;
    }

    /** Sanitise a user query into safe MySQL boolean-mode terms. */
    private function booleanTerms(string $q): string
    {
        $words = preg_split('/\s+/u', $q) ?: [];
        $terms = [];
        foreach ($words as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $word) ?? '';
            if (mb_strlen($clean) >= 2) {
                $terms[] = '+' . $clean . '*';
            }
        }
        return $terms === [] ? '' : implode(' ', $terms);
    }

    /** @return array<int,array<string,mixed>> */
    public function featured(int $limit = 8): array
    {
        return Cache::remember('templates:featured:' . $limit, 600, function () use ($limit): array {
            return $this->hydrateMany($this->db->select(
                'SELECT ' . self::CARD_COLUMNS . ' FROM ' . $this->qualified() . ' t
                 WHERE t.deleted_at IS NULL AND t.is_active = 1 AND t.is_featured = 1
                 ORDER BY t.sort_order ASC, t.use_count DESC LIMIT ' . max(1, min(48, $limit))
            ));
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function related(array $template, int $limit = 6): array
    {
        return $this->hydrateMany($this->db->select(
            'SELECT ' . self::CARD_COLUMNS . ' FROM ' . $this->qualified() . ' t
             WHERE t.deleted_at IS NULL AND t.is_active = 1 AND t.id <> :id
               AND (t.subcategory_id = :sub OR t.category_id = :cat)
             ORDER BY (t.subcategory_id = :sub2) DESC, t.use_count DESC
             LIMIT ' . max(1, min(24, $limit)),
            [
                'id'   => (int) $template['id'],
                'sub'  => $template['subcategory_id'] !== null ? (int) $template['subcategory_id'] : 0,
                'sub2' => $template['subcategory_id'] !== null ? (int) $template['subcategory_id'] : 0,
                'cat'  => (int) $template['category_id'],
            ]
        ));
    }

    public function incrementViews(int $templateId): void
    {
        $this->db->increment($this->table, 'view_count', ['id' => $templateId]);
    }

    public function incrementUses(int $templateId): void
    {
        $this->db->increment($this->table, 'use_count', ['id' => $templateId]);
    }

    /** Duplicate a template with its fields, components and assets. */
    public function duplicate(int $templateId, string $newName, string $newCode, string $newSlug, ?int $userId): int
    {
        return $this->db->transaction(function () use ($templateId, $newName, $newCode, $newSlug, $userId): int {
            $source = $this->find($templateId);
            if ($source === null) {
                throw new \RuntimeException('Template not found.');
            }

            $copy = $source;
            unset($copy['id'], $copy['created_at'], $copy['updated_at'], $copy['deleted_at']);
            $copy['name'] = $newName;
            $copy['code'] = $newCode;
            $copy['slug'] = $newSlug;
            $copy['is_featured'] = 0;
            $copy['use_count'] = 0;
            $copy['view_count'] = 0;
            $copy['created_by'] = $userId;
            $newId = $this->create($copy);

            $fieldRepo = new TemplateFieldRepository();
            foreach ($fieldRepo->forTemplate($templateId) as $field) {
                unset($field['id'], $field['created_at'], $field['updated_at']);
                $field['template_id'] = $newId;
                $fieldRepo->create($field);
            }

            $componentRepo = new TemplateComponentRepository();
            $idMap = [];
            foreach ($componentRepo->forTemplate($templateId) as $component) {
                $oldId = (int) $component['id'];
                unset($component['id'], $component['created_at'], $component['updated_at']);
                $component['template_id'] = $newId;
                $component['parent_id'] = $component['parent_id'] !== null
                    ? ($idMap[(int) $component['parent_id']] ?? null)
                    : null;
                $idMap[$oldId] = $componentRepo->create($component);
            }

            foreach ($this->db->select(
                'SELECT * FROM ' . $this->db->wrap($this->db->table('template_assets')) . ' WHERE template_id = :id',
                ['id' => $templateId]
            ) as $asset) {
                unset($asset['id'], $asset['created_at'], $asset['updated_at']);
                $asset['template_id'] = $newId;
                $this->db->insert('template_assets', $asset);
            }

            return $newId;
        });
    }

    /** @return array{total:int,active:int,premium:int,animated:int} */
    public function stats(): array
    {
        $base = 'SELECT COUNT(*) FROM ' . $this->qualified() . ' WHERE deleted_at IS NULL';
        return [
            'total'    => (int) $this->db->value($base, [], 0),
            'active'   => (int) $this->db->value($base . ' AND is_active = 1', [], 0),
            'premium'  => (int) $this->db->value($base . ' AND is_premium = 1', [], 0),
            'animated' => (int) $this->db->value($base . ' AND has_animation = 1', [], 0),
        ];
    }

    /** @return array<int,array<string,mixed>> most used templates */
    public function mostUsed(int $limit = 10): array
    {
        return $this->db->select(
            'SELECT id, name, code, use_count, thumbnail FROM ' . $this->qualified() . '
             WHERE deleted_at IS NULL ORDER BY use_count DESC LIMIT ' . max(1, min(50, $limit))
        );
    }

    /** Distinct primary colours present in the catalogue (filter chips). */
    public function paletteOptions(): array
    {
        return Cache::remember('templates:palette', 3600, fn (): array => array_map(
            'strval',
            $this->db->column(
                'SELECT color_primary FROM ' . $this->qualified() . '
                 WHERE deleted_at IS NULL AND is_active = 1
                 GROUP BY color_primary ORDER BY COUNT(*) DESC LIMIT 16'
            )
        ));
    }

    public function flushCaches(): void
    {
        Cache::forget('templates:palette');
        for ($i = 1; $i <= 48; $i++) {
            Cache::forget('templates:featured:' . $i);
        }
    }

    /** Slug/code uniqueness helpers used by the admin forms. */
    public function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base !== '' ? $base : 'template';
        $candidate = $slug;
        $i = 1;
        while ($this->exists('slug', $candidate, $ignoreId)) {
            $candidate = $slug . '-' . (++$i);
        }
        return $candidate;
    }

    public function uniqueCode(string $base, ?int $ignoreId = null): string
    {
        $code = strtoupper($base !== '' ? $base : 'TPL');
        $candidate = $code;
        $i = 1;
        while ($this->exists('code', $candidate, $ignoreId)) {
            $candidate = $code . '-' . (++$i);
        }
        return $candidate;
    }
}
