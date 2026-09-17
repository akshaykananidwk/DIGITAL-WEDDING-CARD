<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Lang;
use App\Core\Str;
use App\Core\Url;

/**
 * Page metadata: title, description, canonical, Open Graph, Twitter cards and
 * JSON-LD structured data.
 *
 * Built as a value object so a controller composes it once and the layout
 * renders it, rather than every view guessing at its own tags.
 */
final class SeoService
{
    private string $title = '';
    private string $description = '';
    private string $canonical = '';
    private string $image = '';
    private string $type = 'website';
    private string $robots = 'index, follow';
    /** @var array<int,array<string,mixed>> */
    private array $structuredData = [];
    /** @var array<string,string> */
    private array $alternates = [];

    public static function make(): self
    {
        return new self();
    }

    public function title(string $title, bool $appendSiteName = true): self
    {
        $siteName = (string) (setting('site_name') ?: config('app.name'));
        $title = trim(strip_tags($title));
        $this->title = $appendSiteName && $title !== '' && $title !== $siteName
            ? mb_substr($title, 0, 60) . ' · ' . $siteName
            : ($title !== '' ? $title : $siteName);
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($description)) ?? ''), 0, 160);
        return $this;
    }

    public function canonical(string $url): self
    {
        $this->canonical = $url;
        return $this;
    }

    public function image(string $url): self
    {
        $this->image = $url;
        return $this;
    }

    public function type(string $type): self
    {
        $this->type = in_array($type, ['website', 'article', 'profile', 'event'], true) ? $type : 'website';
        return $this;
    }

    public function noindex(): self
    {
        $this->robots = 'noindex, nofollow';
        return $this;
    }

    public function robots(string $value): self
    {
        $this->robots = $value;
        return $this;
    }

    /** @param array<string,mixed> $data */
    public function structuredData(array $data): self
    {
        $this->structuredData[] = $data;
        return $this;
    }

    /** hreflang alternates for the multilingual pages. */
    public function withLocaleAlternates(string $path): self
    {
        foreach (array_keys(Lang::available()) as $locale) {
            $this->alternates[$locale] = Url::to($path, ['lang' => $locale]);
        }
        return $this;
    }

    /**
     * A breadcrumb trail, as JSON-LD.
     *
     * Google shows the trail in place of the raw URL, which is worth a lot on
     * a deep catalogue: "Home > Kankotri > Gujarati Wedding" reads better in a
     * result than a slug.
     *
     * @param array<int,array{name:string,url:string}> $trail
     */
    public function breadcrumbs(array $trail): self
    {
        $items = [];
        foreach (array_values($trail) as $position => $crumb) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'name'     => (string) $crumb['name'],
                'item'     => (string) $crumb['url'],
            ];
        }
        if ($items === []) {
            return $this;
        }

        return $this->structuredData([
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ]);
    }

    /**
     * A list of what this page shows, as JSON-LD.
     *
     * @param array<int,array{name:string,url:string}> $entries
     */
    public function itemList(array $entries, string $name = ''): self
    {
        $items = [];
        foreach (array_values($entries) as $position => $entry) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'name'     => (string) $entry['name'],
                'url'      => (string) $entry['url'],
            ];
        }
        if ($items === []) {
            return $this;
        }

        $payload = [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'numberOfItems'   => count($items),
            'itemListElement' => $items,
        ];
        if ($name !== '') {
            $payload['name'] = $name;
        }

        return $this->structuredData($payload);
    }

    /**
     * Questions and answers, as JSON-LD.
     *
     * The answers have to be the ones actually on the page - Google's
     * guideline, and the only honest way to do it - so the caller passes the
     * same text it renders.
     *
     * @param array<int,array{question:string,answer:string}> $pairs
     */
    public function faq(array $pairs): self
    {
        $entries = [];
        foreach ($pairs as $pair) {
            $question = trim((string) $pair['question']);
            $answer = trim((string) $pair['answer']);
            if ($question === '' || $answer === '') {
                continue;
            }
            $entries[] = [
                '@type'          => 'Question',
                'name'           => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
            ];
        }
        if ($entries === []) {
            return $this;
        }

        return $this->structuredData([
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entries,
        ]);
    }

    // ------------------------------------------------------------------
    //  Presets
    // ------------------------------------------------------------------

    public static function forHome(): self
    {
        $seo = self::make()
            ->title((string) (setting('site_name') ?: config('app.name')), false)
            ->description((string) (setting('site_description')
                ?: Lang::get('app.tagline')))
            ->canonical(Url::to('/'))
            ->withLocaleAlternates('/');

        $seo->structuredData([
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => (string) (setting('site_name') ?: config('app.name')),
            'url'      => Url::base(),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => Url::to('templates') . '?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ]);

        // Who runs the site. Needed for a knowledge panel and for the logo to
        // appear beside the results.
        $seo->structuredData(array_filter([
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => (string) (setting('site_name') ?: config('app.name')),
            'url'      => Url::base(),
            'logo'     => ($logo = (string) setting('brand_logo', '')) !== ''
                ? Url::to(ltrim($logo, '/'))
                : Url::to('assets/img/apple-touch-icon.png'),
            'areaServed' => 'IN',
        ]));

        $seo->faq(self::homeFaq());

        return $seo;
    }

    /**
     * The questions the home page answers, in the reader's language.
     *
     * Kept here beside the structured data so the two cannot drift: the page
     * renders this list and the JSON-LD describes the same list.
     *
     * @return array<int,array{question:string,answer:string}>
     */
    public static function homeFaq(): array
    {
        $out = [];
        foreach (['free', 'whatsapp', 'languages', 'rsvp', 'pdf', 'time'] as $key) {
            $question = Lang::get('faq.' . $key . '_q');
            $answer = Lang::get('faq.' . $key . '_a');
            if ($question !== 'faq.' . $key . '_q' && $answer !== 'faq.' . $key . '_a') {
                $out[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $out;
    }

    public static function forTemplate(array $template): self
    {
        $name = (string) $template['name'];
        $description = (string) ($template['meta_description'] ?: $template['description'] ?: '');
        if ($description === '') {
            $description = Lang::get('seo.template_description', ['name' => $name]);
        }

        /*
         * The title carries the phrase people actually type - "digital
         * kankotri", "invitation card" - in the language they are browsing in,
         * because a title that only repeats the design's own name matches
         * nothing anybody searches for.
         */
        $seo = self::make()
            ->title((string) ($template['meta_title'] ?: Lang::get('seo.template_title', ['name' => $name])))
            ->description($description)
            ->canonical(Url::to('templates/' . $template['slug']))
            ->type('article');

        $image = (string) ($template['og_image'] ?: $template['thumbnail'] ?: '');
        if ($image !== '') {
            $seo->image(Url::to(ltrim($image, '/')));
        }

        $seo->structuredData([
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $name,
            'description' => Str::limit($description, 300),
            'sku'         => (string) $template['code'],
            'category'    => 'Invitation card template',
            'offers'      => [
                '@type'         => 'Offer',
                'price'         => '0',
                'priceCurrency' => 'INR',
                'availability'  => 'https://schema.org/InStock',
                'url'           => Url::to('templates/' . $template['slug']),
            ],
        ]);

        return $seo;
    }

    public static function forCategory(array $category, ?array $subcategory = null): self
    {
        $name = $subcategory !== null ? (string) $subcategory['name'] : (string) $category['name'];
        $path = $subcategory !== null
            ? 'category/' . $category['slug'] . '/' . $subcategory['slug']
            : 'category/' . $category['slug'];
        $source = $subcategory ?? $category;

        return self::make()
            ->title((string) ($source['meta_title'] ?: Lang::get('seo.category_title', ['name' => $name])))
            ->description((string) ($source['meta_description'] ?: $source['description']
                ?: Lang::get('seo.category_description', ['name' => $name])))
            ->canonical(Url::to($path))
            ->withLocaleAlternates($path);
    }

    /**
     * Metadata for a public invitation.
     *
     * Deliberately noindex by default: a family invitation should not appear
     * in search results unless its owner asks for that.
     */
    public static function forInvitation(array $invitation, TemplateContext $context): self
    {
        $title = (string) ($invitation['meta_title'] ?: $invitation['title']);
        $description = (string) ($invitation['meta_description'] ?: '');
        if ($description === '') {
            $eventDate = $context->longDate('wedding_date') ?: $context->longDate('event_date');
            $venue = (string) ($context->raw('venue') ?? '');
            $description = trim('You are warmly invited. '
                . ($eventDate !== '' ? html_entity_decode($eventDate) . '. ' : '')
                . ($venue !== '' ? $venue . '.' : ''));
        }

        $seo = self::make()
            ->title($title, false)
            ->description($description)
            ->canonical(Url::invite((string) $invitation['slug']))
            ->type('event');

        $image = (string) ($invitation['og_image'] ?? '');
        if ($image !== '') {
            $seo->image(Url::upload(ltrim($image, '/')));
        } else {
            $hero = $context->heroPhotoUrl();
            if ($hero !== '') {
                $seo->image($hero);
            }
        }

        if (!SettingsService::instance()->bool('seo_index_invitations', false)) {
            $seo->noindex();
        }

        $eventIso = $context->eventIso();
        if ($eventIso !== '') {
            $seo->structuredData(array_filter([
                '@context'  => 'https://schema.org',
                '@type'     => 'Event',
                'name'      => $title,
                'startDate' => $eventIso,
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'eventStatus' => 'https://schema.org/EventScheduled',
                'location'  => $context->has('venue') ? [
                    '@type'   => 'Place',
                    'name'    => html_entity_decode($context->get('venue')),
                    'address' => html_entity_decode($context->get('venue_address')),
                ] : null,
                'description' => Str::limit($description, 300),
                'url'         => Url::invite((string) $invitation['slug']),
            ], static fn ($value) => $value !== null));
        }

        return $seo;
    }

    // ------------------------------------------------------------------
    //  Rendering
    // ------------------------------------------------------------------

    /** All the tags, ready to print inside <head>. */
    public function render(): string
    {
        $siteName = (string) (setting('site_name') ?: config('app.name'));
        $title = $this->title !== '' ? $this->title : $siteName;
        $canonical = $this->canonical !== '' ? $this->canonical : Url::current();
        $image = $this->image !== '' ? $this->image : Url::asset('img/og-default.png');

        $tags = [];
        $tags[] = '<title>' . e($title) . '</title>';
        if ($this->description !== '') {
            $tags[] = '<meta name="description" content="' . e($this->description) . '">';
        }
        $tags[] = '<meta name="robots" content="' . e($this->robots) . '">';
        $tags[] = '<link rel="canonical" href="' . e($canonical) . '">';

        foreach ($this->alternates as $locale => $url) {
            $tags[] = '<link rel="alternate" hreflang="' . e($locale) . '" href="' . e($url) . '">';
        }

        $tags[] = '<meta property="og:site_name" content="' . e($siteName) . '">';
        $tags[] = '<meta property="og:type" content="' . e($this->type) . '">';
        $tags[] = '<meta property="og:title" content="' . e($title) . '">';
        if ($this->description !== '') {
            $tags[] = '<meta property="og:description" content="' . e($this->description) . '">';
        }
        $tags[] = '<meta property="og:url" content="' . e($canonical) . '">';
        $tags[] = '<meta property="og:image" content="' . e($image) . '">';
        $tags[] = '<meta property="og:locale" content="' . e(str_replace('-', '_', Lang::htmlLang())) . '">';

        $tags[] = '<meta name="twitter:card" content="summary_large_image">';
        $tags[] = '<meta name="twitter:title" content="' . e($title) . '">';
        if ($this->description !== '') {
            $tags[] = '<meta name="twitter:description" content="' . e($this->description) . '">';
        }
        $tags[] = '<meta name="twitter:image" content="' . e($image) . '">';

        foreach ($this->structuredData as $data) {
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                // JSON-LD is data, not script: escape the closing tag sequence.
                $tags[] = '<script type="application/ld+json">'
                    . str_replace('</', '<\/', $json)
                    . '</script>';
            }
        }

        return implode("\n    ", $tags);
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
