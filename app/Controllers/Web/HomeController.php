<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CategoryRepository;
use App\Repositories\PageRepository;
use App\Repositories\TemplateRepository;
use App\Services\SeoService;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = new CategoryRepository();
        $templates = new TemplateRepository();

        return $this->view('public.home', [
            'seo'        => SeoService::forHome(),
            'categories' => $categories->tree(),
            'featured'   => $templates->featured(8),
            'latest'     => $templates->search(['sort' => 'latest'], 1, 8)['rows'],
            'stats'      => $templates->stats(),
            'footerLinks' => (new PageRepository())->footerLinks(),
        ]);
    }
}
