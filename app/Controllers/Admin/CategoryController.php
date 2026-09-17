<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\ValidationException;
use App\Repositories\CategoryRepository;
use App\Repositories\SubcategoryRepository;
use App\Services\AuditService;
use App\Services\SitemapService;

final class CategoryController extends AdminController
{
    public function __construct(
        private readonly CategoryRepository $categories = new CategoryRepository(),
        private readonly SubcategoryRepository $subcategories = new SubcategoryRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->admin('admin.categories', 'Categories', [
            'tree' => $this->categories->tree(false),
        ]);
    }

    public function store(Request $request): Response
    {
        try {
            $data = $this->validate($request, $this->rules());
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $slug = $this->uniqueSlug($this->categories, (string) ($data['slug'] ?? $data['name']));

        $id = $this->categories->create([
            'name'        => (string) $data['name'],
            'name_gu'     => $data['name_gu'] ?? null,
            'name_hi'     => $data['name_hi'] ?? null,
            'slug'        => $slug,
            'description' => $data['description'] ?? null,
            'icon'        => $data['icon'] ?? null,
            'color'       => (string) ($data['color'] ?? '#C8102E'),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => $request->bool('is_active', true) ? 1 : 0,
            'is_featured' => $request->bool('is_featured') ? 1 : 0,
            'meta_title'  => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ]);

        $this->afterChange();
        AuditService::instance()->log('admin.category.create', 'category', $id, (string) $data['name']);

        return $this->respond($request, true, 'Category created.', 'admin/categories');
    }

    public function update(Request $request): Response
    {
        $id = $request->int('id');
        $category = $this->categories->find($id);
        if ($category === null) {
            throw HttpException::notFound();
        }

        try {
            $data = $this->validate($request, $this->rules($id));
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $update = [
            'name'        => (string) $data['name'],
            'name_gu'     => $data['name_gu'] ?? null,
            'name_hi'     => $data['name_hi'] ?? null,
            'description' => $data['description'] ?? null,
            'icon'        => $data['icon'] ?? null,
            'color'       => (string) ($data['color'] ?? '#C8102E'),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => $request->bool('is_active') ? 1 : 0,
            'is_featured' => $request->bool('is_featured') ? 1 : 0,
            'meta_title'  => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ];
        if (isset($data['slug']) && (string) $data['slug'] !== (string) $category['slug']) {
            $update['slug'] = $this->uniqueSlug($this->categories, (string) $data['slug'], $id);
        }

        $this->categories->update($id, $update);
        $this->afterChange();
        AuditService::instance()->logChanges('admin.category.update', 'category', $id, $category, $update);

        return $this->respond($request, true, 'Category updated.', 'admin/categories');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->int('id');
        $category = $this->categories->find($id);
        if ($category === null) {
            throw HttpException::notFound();
        }

        // Refuse while templates still point at it, rather than orphaning them.
        $templates = (int) $this->categories->db()->value(
            'SELECT COUNT(*) FROM ' . $this->categories->db()->wrap($this->categories->db()->table('templates'))
            . ' WHERE category_id = :id AND deleted_at IS NULL',
            ['id' => $id],
            0
        );
        if ($templates > 0) {
            return $this->respond(
                $request,
                false,
                'This category still has ' . $templates . ' template(s). Move or delete them first.',
                'admin/categories'
            );
        }

        $this->categories->delete($id);
        $this->afterChange();
        AuditService::instance()->log('admin.category.delete', 'category', $id, (string) $category['name']);

        return $this->respond($request, true, 'Category deleted.', 'admin/categories');
    }

    // ------------------------------------------------------------------
    //  Subcategories
    // ------------------------------------------------------------------

    public function storeSubcategory(Request $request): Response
    {
        try {
            $data = $this->validate($request, array_merge($this->rules(), [
                'category_id' => 'required|integer|exists:categories,id',
            ]));
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $id = $this->subcategories->create([
            'category_id' => (int) $data['category_id'],
            'name'        => (string) $data['name'],
            'name_gu'     => $data['name_gu'] ?? null,
            'name_hi'     => $data['name_hi'] ?? null,
            'slug'        => $this->uniqueSlug($this->subcategories, (string) ($data['slug'] ?? $data['name'])),
            'description' => $data['description'] ?? null,
            'icon'        => $data['icon'] ?? null,
            'theme_tags'  => $this->parseTags((string) $request->input('theme_tags', '')),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => $request->bool('is_active', true) ? 1 : 0,
            'meta_title'  => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ]);

        $this->afterChange();
        AuditService::instance()->log('admin.subcategory.create', 'subcategory', $id, (string) $data['name']);

        return $this->respond($request, true, 'Subcategory created.', 'admin/categories');
    }

    public function updateSubcategory(Request $request): Response
    {
        $id = $request->int('id');
        $subcategory = $this->subcategories->find($id);
        if ($subcategory === null) {
            throw HttpException::notFound();
        }

        try {
            $data = $this->validate($request, array_merge($this->rules($id), [
                'category_id' => 'required|integer|exists:categories,id',
            ]));
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $update = [
            'category_id' => (int) $data['category_id'],
            'name'        => (string) $data['name'],
            'name_gu'     => $data['name_gu'] ?? null,
            'name_hi'     => $data['name_hi'] ?? null,
            'description' => $data['description'] ?? null,
            'icon'        => $data['icon'] ?? null,
            'theme_tags'  => $this->parseTags((string) $request->input('theme_tags', '')),
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => $request->bool('is_active') ? 1 : 0,
            'meta_title'  => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ];
        if (isset($data['slug']) && (string) $data['slug'] !== (string) $subcategory['slug']) {
            $update['slug'] = $this->uniqueSlug($this->subcategories, (string) $data['slug'], $id);
        }

        $this->subcategories->update($id, $update);
        $this->afterChange();
        AuditService::instance()->log('admin.subcategory.update', 'subcategory', $id, (string) $data['name']);

        return $this->respond($request, true, 'Subcategory updated.', 'admin/categories');
    }

    public function destroySubcategory(Request $request): Response
    {
        $id = $request->int('id');
        $subcategory = $this->subcategories->find($id);
        if ($subcategory === null) {
            throw HttpException::notFound();
        }

        $db = $this->subcategories->db();
        $templates = (int) $db->value(
            'SELECT COUNT(*) FROM ' . $db->wrap($db->table('templates'))
            . ' WHERE subcategory_id = :id AND deleted_at IS NULL',
            ['id' => $id],
            0
        );
        if ($templates > 0) {
            return $this->respond(
                $request,
                false,
                'This subcategory still has ' . $templates . ' template(s).',
                'admin/categories'
            );
        }

        $this->subcategories->delete($id);
        $this->afterChange();
        AuditService::instance()->log('admin.subcategory.delete', 'subcategory', $id, (string) $subcategory['name']);

        return $this->respond($request, true, 'Subcategory deleted.', 'admin/categories');
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /** @return array<string,string> */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'name'        => 'required|string|min:2|max:120|no_html',
            'name_gu'     => 'nullable|string|max:160|no_html',
            'name_hi'     => 'nullable|string|max:160|no_html',
            'slug'        => 'nullable|string|max:140',
            'description' => 'nullable|string|max:500|safe_text',
            'icon'        => 'nullable|string|max:60|alpha_dash',
            'color'       => 'nullable|hex_color',
            'sort_order'  => 'nullable|integer|min:0|max:9999',
            'meta_title'  => 'nullable|string|max:191|no_html',
            'meta_description' => 'nullable|string|max:300|no_html',
        ];
    }

    private function uniqueSlug(object $repository, string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'item-' . strtolower(Str::shortCode(5));
        }
        $candidate = $slug;
        $i = 1;
        /** @phpstan-ignore-next-line repositories share the exists() contract */
        while ($repository->exists('slug', $candidate, $ignoreId)) {
            $candidate = $slug . '-' . (++$i);
        }
        return $candidate;
    }

    /** @return array<int,string> */
    private function parseTags(string $raw): array
    {
        $parts = preg_split('/[,\n]/u', $raw) ?: [];
        $tags = [];
        foreach ($parts as $part) {
            $tag = strtolower(trim((string) preg_replace('/[^\p{L}\p{N} \-]/u', '', $part)));
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
        return array_values(array_unique($tags));
    }

    private function afterChange(): void
    {
        $this->categories->refreshCounts();
        (new SitemapService())->flush();
    }
}
