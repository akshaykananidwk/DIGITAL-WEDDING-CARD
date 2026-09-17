<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Services\SettingsService;
use App\Services\SitemapService;

/** sitemap.xml, robots.txt, the PWA manifest and the offline page. */
final class SeoController extends Controller
{
    public function sitemap(Request $request): Response
    {
        return Response::xml((new SitemapService())->xml())->cache(3600);
    }

    public function robots(Request $request): Response
    {
        return Response::text((new SitemapService())->robots())->cache(3600);
    }

    /**
     * The service worker.
     *
     * Served through the application rather than as a static file so it always
     * arrives with a JavaScript content type and a root scope header, on hosts
     * whose rewrite rules would otherwise send it here as a 404.
     */
    public function serviceWorker(Request $request): Response
    {
        $path = ROOT_PATH . '/service-worker.js';
        if (!is_file($path)) {
            throw \App\Core\HttpException::notFound();
        }

        return Response::make((string) file_get_contents($path), 200, [
            'Content-Type'           => 'application/javascript; charset=utf-8',
            // Lets a worker served from anywhere control the whole origin.
            'Service-Worker-Allowed' => Url::basePath() . '/',
            'Cache-Control'          => 'no-cache, must-revalidate',
        ]);
    }

    public function manifest(Request $request): Response
    {
        $settings = SettingsService::instance();
        $name = (string) ($settings->get('site_name') ?: config('app.name'));

        $manifest = [
            'name'             => $name,
            'short_name'       => mb_substr($name, 0, 12),
            'description'      => (string) ($settings->get('site_description') ?: config('app.tagline')),
            'start_url'        => Url::to('/dashboard'),
            'scope'            => Url::to('/'),
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#FFF8EE',
            'theme_color'      => (string) ($settings->get('brand_primary') ?: '#C8102E'),
            'lang'             => \App\Core\Lang::htmlLang(),
            'dir'              => 'ltr',
            'categories'       => ['lifestyle', 'social', 'productivity'],
            'icons'            => [
                [
                    'src'     => Url::to('assets/img/icon-192.png'),
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src'     => Url::to('assets/img/icon-512.png'),
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => 'Create invitation',
                    'url'  => Url::to('/create'),
                ],
                [
                    'name' => 'My invitations',
                    'url'  => Url::to('/invitations'),
                ],
            ],
        ];

        return Response::json($manifest)
            ->header('Content-Type', 'application/manifest+json; charset=utf-8')
            ->cache(3600);
    }

    public function offline(Request $request): Response
    {
        return $this->view('errors.offline', [])->cache(86400);
    }
}
