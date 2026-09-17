<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Url;
use App\Repositories\CategoryRepository;
use App\Repositories\InvitationRepository;
use App\Repositories\PageRepository;
use App\Repositories\TemplateRepository;

/**
 * sitemap.xml and robots.txt.
 *
 * Template and category pages are public marketing surface and belong in the
 * sitemap. Individual invitations are indexed only when their owner opts in -
 * a family's invitation is not something to publish to search engines by
 * default.
 */
final class SitemapService
{
    public function __construct(
        private readonly TemplateRepository $templates = new TemplateRepository(),
        private readonly CategoryRepository $categories = new CategoryRepository(),
        private readonly PageRepository $pages = new PageRepository(),
        private readonly InvitationRepository $invitations = new InvitationRepository()
    ) {
    }

    public function xml(): string
    {
        return Cache::remember('sitemap:xml', 3600, function (): string {
            $entries = [];

            $entries[] = $this->entry(Url::to('/'), null, 'daily', '1.0');
            $entries[] = $this->entry(Url::to('templates'), null, 'daily', '0.9');
            $entries[] = $this->entry(Url::to('categories'), null, 'weekly', '0.8');

            foreach ($this->categories->tree() as $category) {
                $entries[] = $this->entry(
                    Url::to('category/' . $category['slug']),
                    $category['updated_at'] ?? null,
                    'weekly',
                    '0.7'
                );
                foreach ($category['subcategories'] as $subcategory) {
                    $entries[] = $this->entry(
                        Url::to('category/' . $category['slug'] . '/' . $subcategory['slug']),
                        $subcategory['updated_at'] ?? null,
                        'weekly',
                        '0.6'
                    );
                }
            }

            // Template detail pages, newest first, capped so the file stays
            // inside the 50,000 URL limit.
            $page = 1;
            $added = 0;
            do {
                $result = $this->templates->search(['active' => true, 'sort' => 'latest'], $page, 500);
                foreach ($result['rows'] as $template) {
                    $entries[] = $this->entry(
                        Url::to('templates/' . $template['slug']),
                        $template['created_at'] ?? null,
                        'monthly',
                        '0.6'
                    );
                    $added++;
                }
                $page++;
            } while ($page <= $result['pages'] && $added < 20000);

            foreach ($this->pages->published() as $staticPage) {
                $entries[] = $this->entry(
                    Url::to('page/' . $staticPage['slug']),
                    $staticPage['updated_at'] ?? null,
                    'monthly',
                    '0.4'
                );
            }

            if (SettingsService::instance()->bool('seo_index_invitations', false)) {
                foreach ($this->invitations->publicForSitemap(10000) as $invitation) {
                    $entries[] = $this->entry(
                        Url::invite((string) $invitation['slug']),
                        $invitation['updated_at'] ?? null,
                        'weekly',
                        '0.5'
                    );
                }
            }

            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
                . implode("\n", $entries) . "\n"
                . '</urlset>';
        });
    }

    private function entry(string $url, ?string $lastModified, string $frequency, string $priority): string
    {
        $out = '  <url>' . "\n" . '    <loc>' . htmlspecialchars($url, ENT_XML1) . '</loc>' . "\n";
        if ($lastModified !== null && $lastModified !== '') {
            $timestamp = strtotime($lastModified);
            if ($timestamp !== false) {
                $out .= '    <lastmod>' . date('Y-m-d', $timestamp) . '</lastmod>' . "\n";
            }
        }
        $out .= '    <changefreq>' . $frequency . '</changefreq>' . "\n"
            . '    <priority>' . $priority . '</priority>' . "\n"
            . '  </url>';
        return $out;
    }

    public function robots(): string
    {
        $settings = SettingsService::instance();
        if (!$settings->bool('seo_allow_indexing', true)) {
            return "User-agent: *\nDisallow: /\n";
        }

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /api/',
            'Disallow: /install',
            'Disallow: /dashboard',
            'Disallow: /builder',
            'Disallow: /invitations',
            'Disallow: /profile',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /cron/',
        ];

        if (!$settings->bool('seo_index_invitations', false)) {
            // Invitations stay out of search results unless explicitly opted in.
            $lines[] = 'Disallow: /invite/';
            $lines[] = 'Disallow: /i/';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: ' . Url::to('sitemap.xml');
        $lines[] = '';

        return implode("\n", $lines);
    }

    public function flush(): void
    {
        Cache::forget('sitemap:xml');
    }
}
