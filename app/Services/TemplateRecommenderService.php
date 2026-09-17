<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Repositories\SubcategoryRepository;
use App\Repositories\TemplateRepository;

/**
 * Template recommendation.
 *
 * A deterministic scorer runs first and always produces a result: it matches
 * event type, theme, language, style and colour against the catalogue. When
 * Gemini is configured it is asked to pick from that shortlist, which keeps
 * the model's job small, cheap and impossible to hallucinate - it can only
 * choose template codes that actually exist.
 *
 * The scorer is also the seam for a future semantic/vector search: replace
 * score() and everything above it keeps working.
 */
final class TemplateRecommenderService
{
    public function __construct(
        private readonly TemplateRepository $templates = new TemplateRepository(),
        private readonly SubcategoryRepository $subcategories = new SubcategoryRepository(),
        private readonly AiService $ai = new AiService()
    ) {
    }

    /**
     * @param array{
     *   event_type?:string, theme?:string, language?:string, style?:string,
     *   color?:string, category_id?:int, subcategory_id?:int, notes?:string
     * } $brief
     * @return array{templates:array<int,array<string,mixed>>,source:string,reason:string}
     */
    public function recommend(array $brief, int $limit = 9): array
    {
        $shortlist = $this->shortlist($brief, max($limit * 3, 24));
        if ($shortlist === []) {
            return ['templates' => [], 'source' => 'none', 'reason' => 'No active templates match that occasion yet.'];
        }

        $ranked = $this->rank($shortlist, $brief);

        if ($this->ai->isConfigured() && FeatureFlagService::instance()->enabled('ai_recommendations', true)) {
            $aiOrder = $this->askAi($ranked, $brief, $limit);
            if ($aiOrder !== null) {
                return ['templates' => $aiOrder['templates'], 'source' => 'ai', 'reason' => $aiOrder['reason']];
            }
        }

        return [
            'templates' => array_slice($ranked, 0, $limit),
            'source'    => 'rules',
            'reason'    => $this->explain($brief),
        ];
    }

    /** Candidate pool from the database, filtered by the obvious facets. */
    private function shortlist(array $brief, int $size): array
    {
        $filters = [
            'active' => true,
            'sort'   => 'popular',
        ];
        if (!empty($brief['category_id'])) {
            $filters['category'] = (int) $brief['category_id'];
        }
        if (!empty($brief['subcategory_id'])) {
            $filters['subcategory'] = (int) $brief['subcategory_id'];
        }
        if (!empty($brief['language'])) {
            $filters['language'] = (string) $brief['language'];
        }

        $result = $this->templates->search($filters, 1, $size);
        if ($result['rows'] !== []) {
            return $result['rows'];
        }

        // Nothing matched the narrow filters: widen to the category alone.
        unset($filters['subcategory'], $filters['language']);
        $result = $this->templates->search($filters, 1, $size);
        if ($result['rows'] !== []) {
            return $result['rows'];
        }

        return $this->templates->search(['active' => true, 'sort' => 'popular'], 1, $size)['rows'];
    }

    /**
     * Score and sort the candidates.
     *
     * @return array<int,array<string,mixed>> each row gains a `match_score`
     */
    public function rank(array $candidates, array $brief): array
    {
        $keywords = $this->briefKeywords($brief);

        foreach ($candidates as $index => $template) {
            $candidates[$index]['match_score'] = $this->score($template, $brief, $keywords);
        }

        usort($candidates, static function (array $a, array $b): int {
            $byScore = ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0);
            return $byScore !== 0 ? $byScore : (($b['use_count'] ?? 0) <=> ($a['use_count'] ?? 0));
        });

        return $candidates;
    }

    /** @param array<int,string> $keywords */
    private function score(array $template, array $brief, array $keywords): int
    {
        $score = 0;

        // Exact taxonomy matches are the strongest signal.
        if (!empty($brief['subcategory_id']) && (int) ($template['subcategory_id'] ?? 0) === (int) $brief['subcategory_id']) {
            $score += 60;
        }
        if (!empty($brief['category_id']) && (int) ($template['category_id'] ?? 0) === (int) $brief['category_id']) {
            $score += 25;
        }

        // Language: an exact match beats a multilingual template, which beats
        // a template in another language.
        $language = (string) ($brief['language'] ?? '');
        if ($language !== '') {
            $templateLanguage = (string) ($template['language'] ?? 'multi');
            if ($templateLanguage === $language) {
                $score += 20;
            } elseif ($templateLanguage === 'multi') {
                $score += 12;
            }
        }

        // Theme and keyword overlap against the template's tags.
        $tags = is_array($template['tags'] ?? null) ? array_map('strval', $template['tags']) : [];
        $tagBlob = mb_strtolower(implode(' ', $tags) . ' ' . (string) ($template['name'] ?? ''));
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($tagBlob, $keyword)) {
                $score += 14;
            }
        }

        // Style preference maps onto the template type.
        $style = mb_strtolower((string) ($brief['style'] ?? ''));
        if ($style !== '') {
            $type = (string) ($template['type'] ?? 'static');
            $score += match (true) {
                $style === 'animated' && in_array($type, ['animated', 'three_d'], true) => 18,
                $style === '3d' && $type === 'three_d'                                  => 22,
                $style === 'minimal' && $type === 'static'                              => 12,
                $style === 'traditional' && $type === 'kankotri'                         => 20,
                $style === 'multi-page' && $type === 'multi_page'                        => 20,
                default                                                                  => 0,
            };
        }

        // Colour preference, compared in RGB space so "red" matches maroon.
        $color = (string) ($brief['color'] ?? '');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
            $distance = $this->colorDistance($color, (string) ($template['color_primary'] ?? '#000000'));
            if ($distance < 60) {
                $score += 20;
            } elseif ($distance < 120) {
                $score += 10;
            }
        }

        // Gentle popularity and quality tiebreakers.
        $score += min(12, (int) floor(((int) ($template['use_count'] ?? 0)) / 25));
        if ((int) ($template['is_featured'] ?? 0) === 1) {
            $score += 6;
        }
        // Premium templates are not pushed while everything is free.
        if ((int) ($template['is_premium'] ?? 0) === 1 && !FeatureFlagService::instance()->enabled('premium_templates')) {
            $score -= 8;
        }

        return $score;
    }

    /** @return array<int,string> */
    private function briefKeywords(array $brief): array
    {
        $blob = mb_strtolower(implode(' ', array_filter([
            (string) ($brief['event_type'] ?? ''),
            (string) ($brief['theme'] ?? ''),
            (string) ($brief['style'] ?? ''),
            (string) ($brief['notes'] ?? ''),
        ], static fn ($value) => $value !== '')));

        if ($blob === '') {
            return [];
        }

        // Theme synonyms, so "Krishna" also matches a Radha-Krishna template.
        $synonyms = [
            'krishna'      => ['krishna', 'radha', 'dwarkadhish', 'flute', 'peacock'],
            'radha'        => ['krishna', 'radha'],
            'ganesh'       => ['ganesh', 'ganpati', 'vighnaharta'],
            'mahadev'      => ['mahadev', 'shiv', 'shiva', 'trishul'],
            'ram'          => ['ram', 'ayodhya', 'sita'],
            'swaminarayan' => ['swaminarayan', 'akshardham'],
            'kankotri'     => ['kankotri', 'gujarati', 'traditional'],
            'garba'        => ['garba', 'navratri', 'dandiya'],
            'royal'        => ['royal', 'regal', 'maharaja', 'gold'],
            'floral'       => ['floral', 'flower', 'rose', 'marigold'],
            'temple'       => ['temple', 'mandir', 'mandala'],
        ];

        $keywords = preg_split('/[^\p{L}\p{N}]+/u', $blob) ?: [];
        $keywords = array_values(array_filter($keywords, static fn ($word) => mb_strlen($word) >= 3));

        foreach ($keywords as $word) {
            if (isset($synonyms[$word])) {
                $keywords = array_merge($keywords, $synonyms[$word]);
            }
        }

        return array_values(array_unique($keywords));
    }

    private function colorDistance(string $a, string $b): float
    {
        $parse = static function (string $hex): array {
            $hex = ltrim($hex, '#');
            if (strlen($hex) !== 6) {
                return [0, 0, 0];
            }
            return [
                (int) hexdec(substr($hex, 0, 2)),
                (int) hexdec(substr($hex, 2, 2)),
                (int) hexdec(substr($hex, 4, 2)),
            ];
        };
        [$r1, $g1, $b1] = $parse($a);
        [$r2, $g2, $b2] = $parse($b);
        return sqrt((($r1 - $r2) ** 2) + (($g1 - $g2) ** 2) + (($b1 - $b2) ** 2));
    }

    private function explain(array $brief): string
    {
        $parts = [];
        if (!empty($brief['event_type'])) {
            $parts[] = (string) $brief['event_type'];
        }
        if (!empty($brief['theme'])) {
            $parts[] = (string) $brief['theme'] . ' theme';
        }
        if (!empty($brief['language'])) {
            $parts[] = strtoupper((string) $brief['language']);
        }
        if (!empty($brief['style'])) {
            $parts[] = (string) $brief['style'] . ' style';
        }
        return $parts === []
            ? 'Most popular templates right now.'
            : 'Matched on ' . implode(', ', $parts) . '.';
    }

    /**
     * Ask Gemini to order the shortlist. It may only return codes we sent it,
     * so an invented template can never appear.
     *
     * @return array{templates:array<int,array<string,mixed>>,reason:string}|null
     */
    private function askAi(array $ranked, array $brief, int $limit): ?array
    {
        $candidates = array_slice($ranked, 0, 20);
        if ($candidates === []) {
            return null;
        }

        $lines = [];
        foreach ($candidates as $template) {
            $tags = is_array($template['tags'] ?? null) ? implode(', ', array_map('strval', $template['tags'])) : '';
            $lines[] = sprintf(
                '- %s | %s | type: %s | language: %s | tags: %s',
                (string) $template['code'],
                (string) $template['name'],
                (string) $template['type'],
                (string) $template['language'],
                mb_substr($tags, 0, 120)
            );
        }

        $prompt = "You are helping someone choose an invitation card design.\n"
            . "Below is the ONLY list of available templates. Choose the {$limit} best matches for the brief.\n"
            . "Return strict JSON: {\"codes\":[\"CODE1\",\"CODE2\"],\"reason\":\"one short sentence\"}\n"
            . "Use only codes from the list. No other text.\n\n"
            . "BRIEF:\n"
            . '- Occasion: ' . mb_substr((string) ($brief['event_type'] ?? 'celebration'), 0, 80) . "\n"
            . '- Theme: ' . mb_substr((string) ($brief['theme'] ?? 'any'), 0, 80) . "\n"
            . '- Language: ' . mb_substr((string) ($brief['language'] ?? 'any'), 0, 20) . "\n"
            . '- Style: ' . mb_substr((string) ($brief['style'] ?? 'any'), 0, 40) . "\n"
            . '- Notes: ' . mb_substr((string) ($brief['notes'] ?? ''), 0, 300) . "\n\n"
            . "TEMPLATES:\n" . implode("\n", $lines);

        $result = $this->ai->complete($prompt, AiService::ACTION_RECOMMEND, (string) ($brief['language'] ?? 'en'));
        if (!$result['ok']) {
            return null;
        }

        $json = $this->extractJson($result['text']);
        if ($json === null || !isset($json['codes']) || !is_array($json['codes'])) {
            Logger::info('AI recommendation returned unusable JSON; falling back to the scorer', [], Logger::API);
            return null;
        }

        $byCode = [];
        foreach ($candidates as $template) {
            $byCode[(string) $template['code']] = $template;
        }

        $selected = [];
        foreach ($json['codes'] as $code) {
            $code = is_scalar($code) ? (string) $code : '';
            if ($code !== '' && isset($byCode[$code])) {
                $selected[] = $byCode[$code];
            }
            if (count($selected) >= $limit) {
                break;
            }
        }
        if ($selected === []) {
            return null;
        }

        // Top up from the deterministic ranking if the model returned too few.
        foreach ($ranked as $template) {
            if (count($selected) >= $limit) {
                break;
            }
            $code = (string) $template['code'];
            $already = false;
            foreach ($selected as $chosen) {
                if ((string) $chosen['code'] === $code) {
                    $already = true;
                    break;
                }
            }
            if (!$already) {
                $selected[] = $template;
            }
        }

        $reason = isset($json['reason']) && is_string($json['reason'])
            ? mb_substr(strip_tags($json['reason']), 0, 200)
            : $this->explain($brief);

        return ['templates' => $selected, 'reason' => $reason];
    }

    /** @return array<string,mixed>|null */
    private function extractJson(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Fast search suggestions for the template gallery's search box.
     *
     * @return array<int,string>
     */
    public function suggestions(string $query, int $limit = 6): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }
        $rows = $this->templates->search(['q' => $query, 'active' => true], 1, $limit)['rows'];
        $out = [];
        foreach ($rows as $row) {
            $out[] = (string) $row['name'];
        }
        // Subcategory names are useful suggestions too.
        foreach ($this->subcategories->allWithCategory() as $sub) {
            if (count($out) >= $limit + 4) {
                break;
            }
            if (stripos((string) $sub['name'], $query) !== false) {
                $out[] = (string) $sub['name'];
            }
        }
        return array_values(array_unique($out));
    }
}
