<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Cache;

final class FontRepository extends BaseRepository
{
    protected string $table = 'fonts';

    /** @return array<int,array<string,mixed>> */
    public function active(): array
    {
        return Cache::remember('fonts:active', 1800, fn (): array => $this->db->select(
            'SELECT * FROM ' . $this->qualified() . ' WHERE is_active = 1 ORDER BY sort_order ASC, name ASC'
        ));
    }

    /** @return array<string,array<string,mixed>> keyed by slug */
    public function keyed(): array
    {
        $out = [];
        foreach ($this->active() as $font) {
            $out[(string) $font['slug']] = $font;
        }
        return $out;
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    /** Fonts with an embeddable TTF, used by the PDF engine. */
    public function pdfCapable(): array
    {
        return $this->db->select(
            'SELECT * FROM ' . $this->qualified() . '
             WHERE is_active = 1 AND pdf_capable = 1 AND file_path IS NOT NULL
             ORDER BY script ASC, name ASC'
        );
    }

    /** Best PDF font for a script, falling back to the default. */
    public function forScript(string $script): ?array
    {
        $row = $this->db->first(
            'SELECT * FROM ' . $this->qualified() . '
             WHERE is_active = 1 AND pdf_capable = 1 AND (script = :script OR script = :multi)
             ORDER BY (script = :script2) DESC, is_default DESC, id ASC LIMIT 1',
            ['script' => $script, 'multi' => 'multi', 'script2' => $script]
        );
        if ($row !== null) {
            return $row;
        }
        return $this->db->first(
            'SELECT * FROM ' . $this->qualified() . ' WHERE is_active = 1 AND pdf_capable = 1 LIMIT 1'
        );
    }

    /** @return array<string,string> slug => display name */
    public function options(): array
    {
        $out = [];
        foreach ($this->active() as $font) {
            $out[(string) $font['slug']] = (string) $font['name'];
        }
        return $out;
    }

    public function flushCache(): void
    {
        Cache::forget('fonts:active');
    }
}
