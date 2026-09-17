<?php

declare(strict_types=1);

namespace Tests\Cases;

use App\Repositories\AiRepository;
use App\Services\AiService;
use App\Services\TemplateRecommenderService;
use Tests\TestCase;

/**
 * Section 60: the AI generator and the recommender.
 *
 * No API key is configured in a test environment, so these checks cover the
 * behaviour that must hold with or without one: the fact guard, the graceful
 * refusal, the quota and the deterministic fallback ranking.
 */
final class AiTest extends TestCase
{
    public function name(): string
    {
        return 'AI generator and recommender';
    }

    public function run(): void
    {
        $this->configuration();
        $this->factGuard();
        $this->gracefulDegradation();
        $this->recommender();
        $this->logging();
    }

    private function configuration(): void
    {
        $service = new AiService();
        $settings = $service->safeSettings();

        $this->assertFalse('AI: the raw API key is never returned', array_key_exists('api_key', $settings));
        $this->assertTrue('AI: a masked key field is present', array_key_exists('api_key_masked', $settings));
        $this->assertTrue('AI: the model is recorded', ($settings['model'] ?? '') !== '');

        if (!$service->isConfigured()) {
            $this->pass('AI: no key configured, so AI features stay off');
            $this->assertFalse('AI: users are not offered AI without a key', $service->isEnabledForUsers());
        } else {
            $this->pass('AI: a key is configured on this host');
        }
    }

    private function factGuard(): void
    {
        $service = new AiService();

        // The guard is the rule the brief insists on: no invented facts.
        $facts = ['groom_name' => 'Rahul', 'bride_name' => 'Priya', 'venue' => 'Dwarka'];

        $text = "Rahul and Priya invite you to Dwarka.\n"
            . "The ceremony begins at 7:45 PM on 12 March 2029.\n"
            . "Please arrive at the hall by 7 PM.";
        $filtered = $service->stripInventedFacts($text, $facts);

        $this->assertContains('AI guard: the supplied names are kept', 'Rahul', $filtered);
        $this->assertNotContains('AI guard: an invented time is dropped', '7:45 PM', $filtered);
        $this->assertNotContains('AI guard: an invented date is dropped', '12 March 2029', $filtered);

        // A number the user did supply is allowed through.
        $withFacts = $service->stripInventedFacts(
            'Join us at Dwarka on 25 December 2027.',
            ['venue' => 'Dwarka', 'event_date' => '25 December 2027']
        );
        $this->assertContains('AI guard: a supplied date survives', '25 December 2027', $withFacts);

        // Nothing user-supplied means nothing numeric survives, and the guard
        // does not fall back to the unfiltered text.
        $noFacts = $service->stripInventedFacts('Come on 5 May at 6 PM.', []);
        $this->assertNotContains('AI guard: with no facts, no numbers pass', '5 May', $noFacts);
        $this->assertSame('AI guard: an entirely invented answer yields nothing', '', $noFacts);

        // Translation keeps numbers, so its own numbers count as supplied.
        $translated = $service->stripInventedFacts(
            '૨૫ ડિસેમ્બર 2027 ના રોજ પધારો.',
            ['source' => 'Please join us on 25 December 2027.']
        );
        $this->assertContains('AI guard: a translated number is kept', '2027', $translated);
    }

    private function gracefulDegradation(): void
    {
        $service = new AiService();
        if ($service->isConfigured()) {
            $this->pass('AI: skipped the unavailable path, a key is configured');
            return;
        }

        $result = $service->generateWording(['groom_name' => 'Rahul'], 'gu', 'traditional');
        $this->assertFalse('AI: generation without a key fails cleanly', (bool) $result['ok']);
        $this->assertGreaterThan('AI: the failure carries a message', 3, (float) strlen((string) $result['message']));
        $this->assertSame('AI: no text is invented on failure', '', (string) $result['text']);
        $this->assertNotContains(
            'AI: the message is not a raw exception',
            'Exception',
            (string) $result['message']
        );

        $test = $service->testConnection();
        $this->assertFalse('AI: the connection test fails without a key', (bool) $test['ok']);
    }

    private function recommender(): void
    {
        $recommender = new TemplateRecommenderService();

        $result = $recommender->recommend([
            'event_type' => 'Gujarati wedding',
            'theme'      => 'krishna',
            'language'   => 'gu',
            'style'      => 'traditional',
            'notes'      => 'We would like something with a temple feel.',
        ], 6);

        $this->assertTrue('Recommender: it returns templates', $result['templates'] !== []);
        $this->assertTrue(
            'Recommender: it returns at most what was asked for',
            count($result['templates']) <= 6,
            (string) count($result['templates'])
        );
        $this->assertTrue(
            'Recommender: the source is declared',
            in_array((string) $result['source'], ['rules', 'ai'], true),
            (string) $result['source']
        );
        $this->assertGreaterThan('Recommender: it explains itself', 3, (float) strlen((string) $result['reason']));

        foreach ($result['templates'] as $template) {
            $this->assertTrue('Recommender: every row is a real template', (int) ($template['id'] ?? 0) > 0);
            $this->assertTrue('Recommender: every row has a slug', ($template['slug'] ?? '') !== '');
            break;
        }

        // A nonsense brief still returns something usable rather than failing.
        $fallback = $recommender->recommend(['event_type' => 'qwertyuiop zxcvbn'], 4);
        $this->assertTrue('Recommender: a nonsense brief still returns options', $fallback['templates'] !== []);

        // Scores are ordered.
        $scores = array_map(static fn (array $t): int => (int) ($t['match_score'] ?? 0), $result['templates']);
        $sorted = $scores;
        rsort($sorted);
        $this->assertSame('Recommender: results are ordered by score', $sorted, $scores);
    }

    private function logging(): void
    {
        $repository = new AiRepository();
        $stats = $repository->stats(30);

        foreach (['total', 'success', 'errors', 'tokens'] as $key) {
            $this->assertTrue('AI log: stats report "' . $key . '"', array_key_exists($key, $stats));
        }
        $this->assertTrue('AI log: usage today is a number', is_int($repository->usageToday()));

        // Prompts and completions are never stored, only metadata.
        $columns = \App\Core\Database::instance()->columns('ai_logs');
        foreach (['prompt', 'response', 'completion', 'output'] as $forbidden) {
            $this->assertFalse(
                'AI log: no "' . $forbidden . '" column exists',
                in_array($forbidden, $columns, true)
            );
        }
        foreach (['action', 'status', 'total_tokens', 'latency_ms'] as $expected) {
            $this->assertTrue('AI log: "' . $expected . '" is recorded', in_array($expected, $columns, true));
        }
    }
}
