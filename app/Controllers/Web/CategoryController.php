<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\CategoryRepository;
use App\Repositories\SubcategoryRepository;
use App\Repositories\TemplateRepository;
use App\Services\SeoService;

final class CategoryController extends Controller
{
    private const PER_PAGE = 24;

    public function __construct(
        private readonly CategoryRepository $categories = new CategoryRepository(),
        private readonly SubcategoryRepository $subcategories = new SubcategoryRepository(),
        private readonly TemplateRepository $templates = new TemplateRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->view('templates.categories', [
            'seo' => SeoService::make()
                ->title(Lang::get('nav.categories'))
                ->description('Browse invitation cards by occasion: weddings, poojas, openings, birthdays and events.')
                ->canonical(Url::to('categories'))
                ->withLocaleAlternates('categories'),
            'categories' => $this->categories->tree(),
        ]);
    }

    public function show(Request $request): Response
    {
        $category = $this->categories->findBySlug((string) $request->param('slug'));
        if ($category === null || (int) $category['is_active'] !== 1) {
            throw HttpException::notFound('That category does not exist.');
        }

        $page = max(1, $request->int('page', 1));
        $result = $this->templates->search([
            'category' => (int) $category['id'],
            'active'   => true,
            'sort'     => 'popular',
        ], $page, self::PER_PAGE);

        return $this->view('templates.category', [
            'seo'         => SeoService::forCategory($category),
            'category'    => $category,
            'subcategory' => null,
            'subcategories' => $this->subcategories->forCategory((int) $category['id']),
            'templates'   => $result['rows'],
            'pagination'  => $this->paginationMeta($result, Url::to('category/' . $category['slug'])),
            'total'       => $result['total'],
        ]);
    }

    public function showSubcategory(Request $request): Response
    {
        $category = $this->categories->findBySlug((string) $request->param('slug'));
        $subcategory = $this->subcategories->findBySlug((string) $request->param('sub'));

        if ($category === null || $subcategory === null
            || (int) $subcategory['category_id'] !== (int) $category['id']) {
            throw HttpException::notFound('That category does not exist.');
        }

        $page = max(1, $request->int('page', 1));
        $result = $this->templates->search([
            'subcategory' => (int) $subcategory['id'],
            'active'      => true,
            'sort'        => 'popular',
        ], $page, self::PER_PAGE);

        return $this->view('templates.category', [
            'seo'         => SeoService::forCategory($category, $subcategory),
            'category'    => $category,
            'subcategory' => $subcategory,
            'subcategories' => $this->subcategories->forCategory((int) $category['id']),
            'templates'   => $result['rows'],
            'pagination'  => $this->paginationMeta(
                $result,
                Url::to('category/' . $category['slug'] . '/' . $subcategory['slug'])
            ),
            'total'       => $result['total'],
        ]);
    }
}
