<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Http\HttpClient;
use App\Core\Lang;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Repositories\AiRepository;

/**
 * Google Gemini integration.
 *
 * Two jobs: compose invitation wording from the details a user already
 * entered, and suggest templates that match the occasion.
 *
 * The prompt is explicit that the model must not invent facts. Names, dates
 * and venues are passed as structured data and the model is told to use only
 * those; anything it returns is then filtered so a hallucinated date cannot
 * reach a card. Nothing is auto-saved - the user reviews and accepts.
 */
final class AiService
{
    public const ACTION_WORDING     = 'invitation_wording';
    public const ACTION_MESSAGE     = 'welcome_message';
    public const ACTION_WHATSAPP    = 'whatsapp_message';
    public const ACTION_RSVP        = 'rsvp_message';
    public const ACTION_TRANSLATE   = 'translate';
    public const ACTION_RECOMMEND   = 'template_recommendation';

    public function __construct(
        private readonly AiRepository $repository = new AiRepository()
    ) {
    }

    // ------------------------------------------------------------------
    //  Availability
    // ------------------------------------------------------------------

    public function isConfigured(): bool
    {
        try {
            $settings = $this->repository->settings();
            return (int) $settings['is_enabled'] === 1 && $this->repository->hasApiKey();
        } catch (\Throwable) {
            return false;
        }
    }

    public function isEnabledForUsers(): bool
    {
        return $this->isConfigured() && FeatureFlagService::instance()->enabled('ai_generator', true);
    }

    /** @return array<string,mixed> settings with the key masked */
    public function safeSettings(): array
    {
        $settings = $this->repository->settings();
        $settings['api_key_masked'] = \App\Core\Str::maskSecret($this->repository->apiKey());
        $settings['has_api_key'] = $this->repository->hasApiKey();
        unset($settings['api_key']);
        return $settings;
    }

    // ------------------------------------------------------------------
    //  Generation
    // ------------------------------------------------------------------

    /**
     * Compose invitation wording.
     *
     * @param array<string,mixed> $facts the only information the model may use
     * @return array{ok:bool,message:string,text:string,tokens:int}
     */
    public function generateWording(array $facts, string $locale = 'en', string $tone = 'traditional'): array
    {
        $prompt = $this->buildWordingPrompt($facts, $locale, $tone);
        $result = $this->complete($prompt, self::ACTION_WORDING, $locale);
        if (!$result['ok']) {
            return $result;
        }
        $result['text'] = $this->stripInventedFacts($result['text'], $facts);
        return $result;
    }

    /** Short WhatsApp-friendly sharing message. */
    public function generateWhatsappMessage(array $facts, string $locale = 'en'): array
    {
        $prompt = $this->systemPrompt($locale)
            . "\n\nTASK: Write a warm WhatsApp sharing message of at most 40 words."
            . "\nEnd with a line inviting the reader to open the link. Do not include a URL."
            . "\nUse ONLY these facts:\n" . $this->factsBlock($facts)
            . "\nReturn plain text, no markdown, at most 3 short lines.";

        $result = $this->complete($prompt, self::ACTION_WHATSAPP, $locale);
        if ($result['ok']) {
            $result['text'] = $this->stripInventedFacts($result['text'], $facts);
        }
        return $result;
    }

    /** A one or two line welcome line for the top of the card. */
    public function generateWelcomeMessage(array $facts, string $locale = 'en'): array
    {
        $prompt = $this->systemPrompt($locale)
            . "\n\nTASK: Write a single warm welcome line of at most 18 words for the top of an invitation card."
            . "\nUse ONLY these facts:\n" . $this->factsBlock($facts)
            . "\nReturn one line of plain text.";

        return $this->complete($prompt, self::ACTION_MESSAGE, $locale);
    }

    public function generateRsvpMessage(array $facts, string $locale = 'en'): array
    {
        $prompt = $this->systemPrompt($locale)
            . "\n\nTASK: Write a polite two-line RSVP request for an invitation card."
            . "\nUse ONLY these facts:\n" . $this->factsBlock($facts)
            . "\nReturn plain text.";

        return $this->complete($prompt, self::ACTION_RSVP, $locale);
    }

    /** Translate wording the user already approved into another language. */
    public function translate(string $text, string $targetLocale): array
    {
        $language = $this->languageName($targetLocale);
        $prompt = "You are a careful translator for Indian wedding invitations.\n"
            . "Translate the text below into {$language}.\n"
            . "Rules: keep every name, number, date and place EXACTLY as written; "
            . "do not add or remove information; keep the line breaks; return only the translation.\n\n"
            . "TEXT:\n" . mb_substr($text, 0, 2000);

        return $this->complete($prompt, self::ACTION_TRANSLATE, $targetLocale);
    }

    // ------------------------------------------------------------------
    //  Prompt construction
    // ------------------------------------------------------------------

    private function systemPrompt(string $locale): string
    {
        $configured = trim((string) ($this->repository->settings()['system_prompt'] ?? ''));
        if ($configured !== '') {
            return $configured . "\n\nWrite in " . $this->languageName($locale) . '.';
        }

        return "You are an expert writer of Indian celebration invitations, especially Gujarati and Hindu "
            . "wedding kankotris.\n"
            . 'Write in ' . $this->languageName($locale) . ".\n"
            . "ABSOLUTE RULES:\n"
            . "1. Use ONLY the facts provided. Never invent or guess a name, date, time, place, "
            . "relationship, phone number or amount.\n"
            . "2. If a fact is missing, leave it out entirely - do not write a placeholder.\n"
            . "3. Keep every provided name, date and place exactly as given, including spelling.\n"
            . "4. Be warm, respectful and culturally appropriate. No emoji unless asked.\n"
            . "5. Return plain text only - no markdown, no headings, no commentary.";
    }

    private function buildWordingPrompt(array $facts, string $locale, string $tone): string
    {
        $toneHint = match ($tone) {
            'modern'      => 'Contemporary and warm, simple sentences.',
            'royal'       => 'Formal and regal, dignified phrasing.',
            'minimal'     => 'Very short and understated.',
            'religious'   => 'Devotional, with respectful references to blessings.',
            default       => 'Traditional Indian invitation style, warm and respectful.',
        };

        return $this->systemPrompt($locale)
            . "\n\nTONE: " . $toneHint
            . "\n\nTASK: Write the invitation wording for the celebration described below."
            . "\nStructure: an opening blessing line, then the invitation sentence naming the people, "
            . "then a closing line requesting the guest's presence."
            . "\nAt most 70 words in total. Do not repeat the date and venue - they are printed separately."
            . "\n\nFACTS (use only these):\n" . $this->factsBlock($facts);
    }

    /** @param array<string,mixed> $facts */
    private function factsBlock(array $facts): string
    {
        $labels = [
            'event_type'     => 'Occasion',
            'groom_name'     => 'Groom',
            'bride_name'     => 'Bride',
            'groom_parents'  => "Groom's parents",
            'bride_parents'  => "Bride's parents",
            'celebrant_name' => 'Person being celebrated',
            'business_name'  => 'Business name',
            'host_name'      => 'Host',
            'relation'       => 'Relationship to the host',
            'event_date'     => 'Date',
            'event_time'     => 'Time',
            'venue'          => 'Venue',
            'venue_address'  => 'Address',
            'city'           => 'City',
            'theme'          => 'Theme',
            'notes'          => 'Extra notes from the host',
        ];

        $lines = [];
        foreach ($labels as $key => $label) {
            $value = $facts[$key] ?? null;
            if (!is_scalar($value)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            // Keep the prompt clean and bounded.
            $lines[] = '- ' . $label . ': ' . mb_substr(strip_tags($value), 0, 200);
        }

        return $lines === [] ? '- (no details supplied)' : implode("\n", $lines);
    }

    private function languageName(string $locale): string
    {
        return match ($locale) {
            'gu' => 'Gujarati (ગુજરાતી script)',
            'hi' => 'Hindi (Devanagari script)',
            default => 'English',
        };
    }

    // ------------------------------------------------------------------
    //  Fact guard
    // ------------------------------------------------------------------

    /**
     * Remove sentences containing a date, time or phone number that was not
     * supplied. The model is told not to invent facts; this makes it true.
     */
    private function stripInventedFacts(string $text, array $facts): string
    {
        $supplied = [];
        foreach ($facts as $value) {
            if (is_scalar($value)) {
                $supplied[] = mb_strtolower((string) $value);
            }
        }
        $suppliedBlob = implode(' | ', $supplied);

        $sentences = preg_split('/(?<=[.!?।])\s+/u', $text) ?: [$text];
        $kept = [];
        foreach ($sentences as $sentence) {
            $hasNumber = preg_match('/\b\d{1,4}\b/u', $sentence) === 1;
            if (!$hasNumber) {
                $kept[] = $sentence;
                continue;
            }
            preg_match_all('/\d{1,4}/u', $sentence, $matches);
            $allKnown = true;
            foreach ($matches[0] as $number) {
                if (!str_contains($suppliedBlob, (string) $number)) {
                    $allKnown = false;
                    break;
                }
            }
            if ($allKnown) {
                $kept[] = $sentence;
                continue;
            }
            Logger::info('AI output: dropped a sentence containing an unsupported number', [], Logger::API);
        }

        $result = trim(implode(' ', $kept));
        return $result !== '' ? $result : trim($text);
    }

    // ------------------------------------------------------------------
    //  Transport
    // ------------------------------------------------------------------

    /**
     * Call Gemini and return the text.
     *
     * @return array{ok:bool,message:string,text:string,tokens:int}
     */
    public function complete(string $prompt, string $action, string $locale = 'en'): array
    {
        $started = microtime(true);

        if (!$this->isConfigured()) {
            return $this->fail($action, $locale, 'error', 'AI features are not configured yet.');
        }

        $settings = $this->repository->settings();
        $userId = Auth::id();

        // Per-user and per-IP rate limits, plus the configured daily cap.
        $identity = $userId !== null ? 'u' . $userId : 'ip' . Request::clientIp();
        $limit = RateLimiter::hit('ai', $identity, 20, 3600);
        if (!$limit['allowed']) {
            return $this->fail($action, $locale, 'quota', 'You have used the AI helper several times. Please try again later.');
        }

        $dailyLimit = (int) $settings['daily_limit'];
        if ($dailyLimit > 0 && $this->repository->usageToday() >= $dailyLimit) {
            return $this->fail($action, $locale, 'quota', 'The daily AI limit has been reached. Please try again tomorrow.');
        }

        $endpoint = rtrim((string) ($settings['endpoint'] ?: Config::get('ai.endpoint')), '/');
        $model = preg_replace('/[^A-Za-z0-9\.\-]/', '', (string) $settings['model']) ?: 'gemini-2.0-flash';
        $url = $endpoint . '/' . $model . ':generateContent';

        $payload = [
            'contents' => [[
                'role'  => 'user',
                'parts' => [['text' => mb_substr($prompt, 0, 8000)]],
            ]],
            'generationConfig' => [
                'temperature'     => (float) $settings['temperature'],
                'topP'            => (float) $settings['top_p'],
                'maxOutputTokens' => max(64, min(8192, (int) $settings['max_tokens'])),
            ],
            // Invitation wording is benign; keep the default safety posture.
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ],
        ];

        $client = new HttpClient(max(5, min(120, (int) $settings['timeout'])));

        try {
            $response = $client->postJson($url, $payload, [
                'x-goog-api-key' => $this->repository->apiKey(),
                'Accept'         => 'application/json',
            ]);
        } catch (\Throwable $e) {
            return $this->fail($action, $locale, 'error', 'Could not reach the AI service: ' . $e->getMessage());
        }

        $latency = (int) round((microtime(true) - $started) * 1000);

        if ($response['error'] !== null) {
            return $this->fail($action, $locale, 'error', $this->friendlyTransportError($response['error']), $latency);
        }
        if ($response['status'] === 429) {
            return $this->fail($action, $locale, 'quota', 'The AI service is rate limiting requests. Please try again shortly.', $latency);
        }
        if ($response['status'] === 401 || $response['status'] === 403) {
            return $this->fail($action, $locale, 'error', 'The AI API key was rejected. Please check the AI settings.', $latency);
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $detail = $this->extractApiError($response['body']);
            return $this->fail(
                $action,
                $locale,
                'error',
                'The AI service returned an error' . ($detail !== '' ? ': ' . $detail : '.'),
                $latency
            );
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return $this->fail($action, $locale, 'error', 'The AI service returned an unreadable response.', $latency);
        }

        $blockReason = $decoded['promptFeedback']['blockReason'] ?? null;
        if (is_string($blockReason) && $blockReason !== '') {
            return $this->fail($action, $locale, 'blocked', 'The AI declined this request. Please rephrase the details.', $latency);
        }

        $text = $this->extractText($decoded);
        if ($text === '') {
            return $this->fail($action, $locale, 'error', 'The AI returned an empty response. Please try again.', $latency);
        }

        $usage = $decoded['usageMetadata'] ?? [];
        $this->repository->log([
            'user_id'           => $userId,
            'action'            => $action,
            'model'             => $model,
            'locale'            => $locale,
            'prompt_tokens'     => (int) ($usage['promptTokenCount'] ?? 0),
            'completion_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
            'total_tokens'      => (int) ($usage['totalTokenCount'] ?? 0),
            'latency_ms'        => $latency,
            'status'            => 'success',
        ]);

        return [
            'ok'      => true,
            'message' => 'Generated.',
            'text'    => $this->cleanOutput($text),
            'tokens'  => (int) ($usage['totalTokenCount'] ?? 0),
        ];
    }

    private function extractText(array $decoded): string
    {
        $parts = $decoded['candidates'][0]['content']['parts'] ?? [];
        if (!is_array($parts)) {
            return '';
        }
        $out = '';
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $out .= $part['text'];
            }
        }
        return trim($out);
    }

    private function extractApiError(string $body): string
    {
        $decoded = json_decode($body, true);
        $message = $decoded['error']['message'] ?? null;
        return is_string($message) ? mb_substr(strip_tags($message), 0, 200) : '';
    }

    private function friendlyTransportError(string $error): string
    {
        $lower = strtolower($error);
        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            return 'The AI service took too long to respond. Please try again.';
        }
        if (str_contains($lower, 'ssl') || str_contains($lower, 'certificate')) {
            return 'A secure connection to the AI service could not be established.';
        }
        if (str_contains($lower, 'resolve') || str_contains($lower, 'could not connect')) {
            return 'The server could not reach the AI service. Check outbound internet access.';
        }
        return 'The AI request failed. Please try again.';
    }

    /** Strip markdown fences and stray quotes the model sometimes adds. */
    private function cleanOutput(string $text): string
    {
        $text = (string) preg_replace('/^```[a-z]*\s*|\s*```$/mi', '', trim($text));
        $text = (string) preg_replace('/^\s*[*#>-]+\s?/mu', '', $text);
        $text = trim($text, " \t\n\r\0\x0B\"'");
        return mb_substr($text, 0, 4000);
    }

    /** @return array{ok:false,message:string,text:string,tokens:int} */
    private function fail(string $action, string $locale, string $status, string $message, int $latency = 0): array
    {
        try {
            $this->repository->log([
                'user_id'       => Auth::id(),
                'action'        => $action,
                'locale'        => $locale,
                'latency_ms'    => $latency,
                'status'        => $status,
                'error_message' => mb_substr($message, 0, 500),
            ]);
        } catch (\Throwable) {
            // Logging a failure must not mask the failure itself.
        }
        return ['ok' => false, 'message' => $message, 'text' => '', 'tokens' => 0];
    }

    /** Admin "test connection" button. */
    public function testConnection(): array
    {
        if (!$this->repository->hasApiKey()) {
            return ['ok' => false, 'message' => 'No API key has been saved yet.'];
        }
        $settings = $this->repository->settings();
        $wasEnabled = (int) $settings['is_enabled'] === 1;
        if (!$wasEnabled) {
            // Allow a test before switching the feature on.
            $this->repository->saveSettings(['is_enabled' => 1]);
        }

        $result = $this->complete(
            'Reply with exactly: CONNECTION OK',
            'connection_test',
            Lang::locale()
        );

        if (!$wasEnabled) {
            $this->repository->saveSettings(['is_enabled' => 0]);
        }

        return [
            'ok'      => $result['ok'],
            'message' => $result['ok']
                ? 'Connection successful. Model replied: ' . mb_substr($result['text'], 0, 60)
                : $result['message'],
        ];
    }
}
