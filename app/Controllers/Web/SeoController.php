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
