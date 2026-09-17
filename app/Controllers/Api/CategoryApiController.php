<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CategoryRepository;

final class CategoryApiController extends Controller
{
    public function index(Request $request): Response
    {
        $tree = (new CategoryRepository())->tree();

        $data = array_map(static function (array $category): array {
            return [
                'id'       => (int) $category['id'],
                'name'     => (string) $category['name'],
                'name_gu'  => (string) ($category['name_gu'] ?? ''),
                'name_hi'  => (string) ($category['name_hi'] ?? ''),
                'slug'     => (string) $category['slug'],
                'icon'     => (string) ($category['icon'] ?? ''),
                'color'    => (string) $category['color'],
                'templates' => (int) $category['template_count'],
                'subcategories' => array_map(static fn (array $sub): array => [
                    'id'        => (int) $sub['id'],
                    'name'      => (string) $sub['name'],
                    'name_gu'   => (string) ($sub['name_gu'] ?? ''),
                    'name_hi'   => (string) ($sub['name_hi'] ?? ''),
                    'slug'      => (string) $sub['slug'],
                    'tags'      => $sub['theme_tags'] ?? [],
                    'templates' => (int) $sub['template_count'],
                ], $category['subcategories']),
            ];
        }, $tree);

        return $this->success($data, '', ['count' => count($data)]);
    }
}
