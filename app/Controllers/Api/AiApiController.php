<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Services\AiService;
use App\Services\FeatureFlagService;
use App\Services\TemplateRecommenderService;

/**
 * AI endpoints used by the builder's "Write it for me" buttons.
 *
 * Nothing is saved automatically: the response is text the user reviews,
 * edits and accepts.
 */
final class AiApiController extends Controller
{
    public function __construct(
        private readonly AiService $ai = new AiService()
    ) {
    }

    public function wording(Request $request): Response
    {
        if (!$this->available()) {
            return $this->error('The AI helper is not available right now.', 503);
        }

        $result = $this->ai->generateWording(
            $this->facts($request),
            $this->locale($request),
            (string) $request->input('tone', 'traditional')
        );

        return $result['ok']
            ? $this->success(['text' => $result['text'], 'tokens' => $result['tokens']], $result['message'])
            : $this->error($result['message'], 422);
    }

    public function message(Request $request): Response
    {
        if (!$this->available()) {
            return $this->error('The AI helper is not available right now.', 503);
        }
        $result = $this->ai->generateWelcomeMessage($this->facts($request), $this->locale($request));
        return $result['ok']
            ? $this->success(['text' => $result['text']], $result['message'])
            : $this->error($result['message'], 422);
    }

    public function whatsapp(Request $request): Response
    {
        if (!$this->available()) {
            return $this->error('The AI helper is not available right now.', 503);
        }
        $result = $this->ai->generateWhatsappMessage($this->facts($request), $this->locale($request));
        return $result['ok']
            ? $this->success(['text' => $result['text']], $result['message'])
            : $this->error($result['message'], 422);
    }

    public function translate(Request $request): Response
    {
        if (!$this->available()) {
            return $this->error('The AI helper is not available right now.', 503);
        }
        $text = mb_substr((string) $request->input('text', ''), 0, 2000);
        if (trim($text) === '') {
            return $this->error('Nothing to translate.', 422);
        }
        $result = $this->ai->translate($text, $this->locale($request));
        return $result['ok']
            ? $this->success(['text' => $result['text']], $result['message'])
            : $this->error($result['message'], 422);
    }

    /** Template suggestions; always answers, with or without AI. */
    public function recommend(Request $request): Response
    {
        $brief = [
            'event_type' => mb_substr((string) $request->input('event_type', ''), 0, 80),
            'theme'      => mb_substr((string) $request->input('theme', ''), 0, 80),
            'style'      => mb_substr((string) $request->input('style', ''), 0, 40),
            'color'      => (string) $request->input('color', ''),
            'language'   => $this->locale($request),
            'notes'      => mb_substr((string) $request->input('notes', ''), 0, 500),
            'category_id'    => $request->int('category_id'),
            'subcategory_id' => $request->int('subcategory_id'),
        ];

        $result = (new TemplateRecommenderService())->recommend($brief, max(3, min(18, $request->int('limit', 9))));

        return $this->success([
            'templates' => array_map(static fn (array $template): array => [
                'id'      => (int) $template['id'],
                'code'    => (string) $template['code'],
                'name'    => (string) $template['name'],
                'slug'    => (string) $template['slug'],
                'type'    => (string) $template['type'],
                'color'   => (string) $template['color_primary'],
                'score'   => (int) ($template['match_score'] ?? 0),
                'preview_url' => \App\Core\Url::to('templates/' . $template['slug'] . '/preview'),
            ], $result['templates']),
            'source' => $result['source'],
            'reason' => $result['reason'],
        ]);
    }

    private function available(): bool
    {
        return $this->ai->isConfigured() && FeatureFlagService::instance()->enabled('ai_generator', true);
    }

    private function locale(Request $request): string
    {
        $locale = (string) $request->input('locale', Lang::locale());
        return in_array($locale, ['en', 'gu', 'hi'], true) ? $locale : 'en';
    }

    /**
     * Only the fields the user supplied are passed to the model, trimmed and
     * length-capped. Nothing else about the account is sent.
     *
     * @return array<string,string>
     */
    private function facts(Request $request): array
    {
        $allowed = [
            'event_type', 'groom_name', 'bride_name', 'groom_parents', 'bride_parents',
            'celebrant_name', 'business_name', 'host_name', 'relation',
            'event_date', 'event_time', 'venue', 'venue_address', 'city', 'theme', 'notes',
        ];

        $facts = [];
        $input = $request->array('facts');
        foreach ($allowed as $key) {
            $value = $input[$key] ?? $request->input($key);
            if (!is_scalar($value)) {
                continue;
            }
            $value = trim(strip_tags((string) $value));
            if ($value !== '') {
                $facts[$key] = mb_substr($value, 0, 300);
            }
        }
        return $facts;
    }
}
