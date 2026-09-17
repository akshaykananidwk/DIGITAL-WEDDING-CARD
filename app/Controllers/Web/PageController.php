<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\PageRepository;
use App\Services\SeoService;

final class PageController extends Controller
{
    public function show(Request $request): Response
    {
        $slug = (string) $request->param('slug');
        $page = (new PageRepository())->findPublished($slug);
        if ($page === null) {
            throw HttpException::notFound('That page does not exist.');
        }

        return $this->view('pages.show', [
            'seo' => SeoService::make()
                ->title((string) ($page['meta_title'] ?: $page['title']))
                ->description((string) ($page['meta_description'] ?: $page['excerpt']))
                ->canonical(Url::to('page/' . $page['slug']))
                ->type('article'),
            'page' => $page,
        ]);
    }
}
