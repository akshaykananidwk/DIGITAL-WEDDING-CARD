<?php

declare(strict_types=1);

namespace Tests\Cases;

use App\Core\Auth;
use App\Core\Database;
use App\Repositories\InvitationRepository;
use App\Repositories\TemplateRepository;
use App\Repositories\UserRepository;
use App\Services\InvitationService;
use App\Services\SlugService;
use Tests\TestCase;

/** Section 60: the invitation lifecycle, slugs, RSVP and analytics recording. */
final class InvitationTest extends TestCase
{
    private InvitationService $service;
    private InvitationRepository $invitations;
    private int $userId = 0;
    /** @var array<int,int> */
    private array $created = [];

    public function name(): string
    {
        return 'Invitations, slugs and RSVP';
    }

    public function run(): void
    {
        $this->service = new InvitationService();
        $this->invitations = new InvitationRepository();

        $users = new UserRepository();
        $this->userId = $users->create([
            'name'     => 'Lifecycle Tester',
            'email'    => 'lifecycle-' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => Auth::hash('LifecyclePass#2026'),
            'role_id'  => (int) ((new \App\Repositories\RoleRepository())->findBySlug('user')['id'] ?? 0),
            'status'   => 'active',
            'locale'   => 'gu',
        ]);

        try {
            $invitation = $this->creation();
            $invitation = $this->content($invitation);
            $invitation = $this->publishing($invitation);
            $this->slugs($invitation);
            $this->rsvp($invitation);
            $this->analytics($invitation);
            $this->duplication($invitation);
            $this->deletion($invitation);
        } finally {
            $this->cleanUp();
        }
    }

    /** @return array<string,mixed> */
    private function creation(): array
    {
        $template = (new TemplateRepository())->search(['active' => true], 1, 1)['rows'][0] ?? [];
        $invitation = $this->service->createFromTemplate($this->userId, (int) $template['id'], 'Suite Wedding');
        $this->created[] = (int) $invitation['id'];

        $this->assertGreaterThan('Lifecycle: an invitation is created', 0, (float) $invitation['id']);
        $this->assertSame('Lifecycle: it starts as a draft', 'draft', (string) $invitation['status']);
        $this->assertMatches('Lifecycle: it gets a URL-safe slug', '/^[a-z0-9\-]+$/', (string) $invitation['slug']);
        $this->assertMatches('Lifecycle: it gets a short code', '/^[A-Z0-9]{4,12}$/', (string) $invitation['short_code']);
        $this->assertSame('Lifecycle: it belongs to its creator', $this->userId, (int) $invitation['user_id']);

        $sections = (new \App\Repositories\InvitationSectionRepository())->forInvitation((int) $invitation['id']);
        $this->assertGreaterThan('Lifecycle: default sections are created', 5, (float) count($sections));

        return $invitation;
    }

    /** @return array<string,mixed> */
    private function content(array $invitation): array
    {
        $result = $this->service->saveContent($invitation, [
            'groom_name' => 'Rahul',
            'bride_name' => 'Priya',
            'venue'      => 'Dwarkadhish Mandir',
            'unknown_key_that_should_be_ignored' => 'x',
        ]);
        $this->assertSame('Content: no validation errors for good input', [], $result['errors']);
        $this->assertGreaterThan('Content: values are saved', 2, (float) $result['saved']);

        $stored = (new \App\Repositories\InvitationDataRepository())->forInvitation((int) $invitation['id']);
        $this->assertSame('Content: a value round trips', 'Rahul', (string) ($stored['groom_name'] ?? ''));
        $this->assertFalse(
            'Content: an unknown field key is ignored',
            array_key_exists('unknown_key_that_should_be_ignored', $stored)
        );

        $rejected = $this->service->saveContent($invitation, ['groom_name' => '<script>alert(1)</script>']);
        $this->assertTrue('Content: markup in a text field is rejected', $rejected['errors'] !== []);

        $tooLong = $this->service->saveContent($invitation, ['groom_name' => str_repeat('a', 5000)]);
        $this->assertTrue('Content: an over-long value is rejected', $tooLong['errors'] !== []);

        return (array) $this->invitations->find((int) $invitation['id']);
    }

    /** @return array<string,mixed> */
    private function publishing(array $invitation): array
    {
        $missing = $this->service->missingRequiredFields($invitation);
        $result = $this->service->publish($invitation);

        if ($missing !== []) {
            // Required fields not yet filled: publishing must refuse and say why.
            $this->assertFalse('Publishing: refused while required fields are empty', (bool) $result['ok']);
            $this->assertTrue('Publishing: the missing fields are reported', $result['missing'] !== []);

            $values = [];
            foreach ((new \App\Repositories\TemplateFieldRepository())->forTemplate((int) $invitation['template_id'], true) as $field) {
                if ((int) $field['is_required'] !== 1) {
                    continue;
                }
                $values[(string) $field['field_key']] = match ((string) $field['type']) {
                    'date'     => date('Y-m-d', strtotime('+120 days')),
                    'time'     => '11:30',
                    'datetime' => date('Y-m-d H:i', strtotime('+120 days')),
                    'number'   => '2',
                    'phone'    => '9876543210',
                    'email'    => 'host@example.test',
                    'url', 'location' => 'https://maps.google.com/?q=Dwarka',
                    default    => 'Test value',
                };
            }
            $this->service->saveContent($invitation, $values);
            $invitation = (array) $this->invitations->find((int) $invitation['id']);
            $result = $this->service->publish($invitation);
        }

        $this->assertTrue('Publishing: succeeds once required fields are filled', (bool) $result['ok']);

        $invitation = (array) $this->invitations->find((int) $invitation['id']);
        $this->assertSame('Publishing: the status is published', 'published', (string) $invitation['status']);
        $this->assertTrue('Publishing: published_at is set', $invitation['published_at'] !== null);

        $this->assertTrue(
            'Publishing: the card is renderable by slug',
            $this->invitations->findForRender((string) $invitation['slug']) !== null
        );

        $this->service->unpublish($invitation);
        $unpublished = (array) $this->invitations->find((int) $invitation['id']);
        $this->assertSame('Publishing: unpublish takes it offline', 'unpublished', (string) $unpublished['status']);

        $this->service->publish($unpublished);
        return (array) $this->invitations->find((int) $invitation['id']);
    }

    private function slugs(array $invitation): void
    {
        $slugs = new SlugService();

        $this->assertSame(
            'Slugs: a Gujarati title is transliterated or falls back safely',
            1,
            preg_match('/^[a-z0-9\-]+$/', $slugs->forTitle('રાહુલ વેડ્સ પ્રિયા'))
        );
        $this->assertNotContains('Slugs: no spaces survive', ' ', $slugs->forTitle('Rahul weds Priya'));
        $this->assertNotContains('Slugs: no slashes survive', '/', $slugs->forTitle('a/b'));

        // Reserved words cannot be taken.
        $reserved = $slugs->forTitle('admin');
        $this->assertFalse('Slugs: a reserved word is not handed out', $reserved === 'admin');

        // Collisions get a suffix rather than overwriting.
        $first = $slugs->fromUserInput('suite-collision-test');
        $db = Database::instance();
        $db->insert('invitations', [
            'user_id'     => $this->userId,
            'template_id' => (int) $invitation['template_id'],
            'title'       => 'Collision holder',
            'slug'        => $first,
            'short_code'  => strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
            'status'      => 'draft',
            'language'    => 'gu',
            'created_at'  => Database::now(),
            'updated_at'  => Database::now(),
        ]);
        $holderId = (int) $db->pdo()->lastInsertId();
        $this->created[] = $holderId;

        $second = $slugs->fromUserInput('suite-collision-test');
        $this->assertFalse('Slugs: a taken slug is not reissued', $first === $second);
        $this->assertMatches('Slugs: the alternative is still URL-safe', '/^[a-z0-9\-]+$/', $second);

        // Changing a slug is allowed, and validated.
        $changed = $this->service->changeSlug($invitation, 'suite-renamed-card');
        $this->assertSame('Slugs: a slug can be changed', 'suite-renamed-card', $changed);
        // A traversal attempt is sanitised into an ordinary slug rather than
        // rejected, so the link is always safe whatever was typed.
        $sanitised = $this->service->changeSlug($invitation, '../../etc/passwd');
        $this->assertMatches('Slugs: a traversal attempt is sanitised', '/^[a-z0-9\-]+$/', $sanitised);
        $this->assertNotContains('Slugs: no dots survive sanitising', '.', $sanitised);

        $this->assertThrows(
            'Slugs: a too-short slug is refused',
            fn () => $this->service->changeSlug($invitation, '-'),
            'at least 3'
        );
        $this->assertThrows(
            'Slugs: a reserved slug is refused',
            fn () => $this->service->changeSlug($invitation, 'admin'),
            'reserved'
        );
    }

    private function rsvp(array $invitation): void
    {
        $invitation = (array) $this->invitations->find((int) $invitation['id']);
        $rsvp = new \App\Services\RsvpService();

        $result = $rsvp->submit($invitation, [
            'name'     => 'Suite Guest',
            'phone'    => '9876543210',
            'response' => 'yes',
            'guests'   => '4',
            'message'  => 'Congratulations!',
        ]);
        $this->assertTrue('RSVP: a valid response is accepted', (bool) $result['ok'], (string) $result['message']);

        $invalid = $rsvp->submit($invitation, ['name' => '', 'response' => 'maybe']);
        $this->assertFalse('RSVP: a response without a name is rejected', (bool) $invalid['ok']);

        $badResponse = $rsvp->submit($invitation, ['name' => 'X', 'response' => 'definitely']);
        $this->assertFalse('RSVP: an unknown response value is rejected', (bool) $badResponse['ok']);

        $summary = (new \App\Repositories\RsvpRepository())->summary((int) $invitation['id']);
        $this->assertSame('RSVP: the summary counts the response', 1, (int) $summary['yes']);
        $this->assertSame('RSVP: the guest count is totalled', 4, (int) $summary['guests']);

        $csv = $rsvp->toCsv((int) $invitation['id']);
        $this->assertContains('RSVP: the export contains the guest', 'Suite Guest', $csv);
        $this->assertNotContains('RSVP: the export carries no IP address', '127.0.0.1', $csv);
    }

    private function analytics(array $invitation): void
    {
        $invitation = (array) $this->invitations->find((int) $invitation['id']);
        $analytics = new \App\Services\AnalyticsService();

        $before = (int) $invitation['view_count'];
        $analytics->recordView($invitation);
        $after = (int) ((array) $this->invitations->find((int) $invitation['id']))['view_count'];
        $this->assertGreaterThan('Analytics: a view is counted', (float) $before, (float) $after);

        $analytics->recordShare($invitation, 'whatsapp');
        $analytics->recordDownload($invitation, 'pdf');

        $report = $analytics->report($invitation, '30d');
        $this->assertGreaterThan('Analytics: the report counts views', 0, (float) $report['lifetime']['views']);
        $this->assertGreaterThan('Analytics: the report counts shares', 0, (float) $report['lifetime']['shares']);
        $this->assertGreaterThan('Analytics: the report counts downloads', 0, (float) $report['lifetime']['downloads']);
        $this->assertSame('Analytics: the series covers 30 days', 30, count($report['series']['labels']));

        // Privacy: a visitor hash, never an address.
        $hash = $analytics->visitorHash((int) $invitation['id']);
        $this->assertMatches('Analytics: the visitor id is a hash', '/^[0-9a-f]{32}$/', $hash);
        $this->assertFalse(
            'Analytics: the same address hashes differently per invitation',
            $hash === $analytics->visitorHash((int) $invitation['id'] + 1)
        );

        $db = Database::instance();
        $columns = $db->columns('invitation_views');
        $this->assertFalse(
            'Analytics: the views table has no ip_address column',
            in_array('ip_address', $columns, true)
        );

        $csv = $analytics->toCsv($invitation, '30d');
        $this->assertContains('Analytics: CSV export has a header row', 'views', strtolower($csv));
    }

    private function duplication(array $invitation): void
    {
        $invitation = (array) $this->invitations->find((int) $invitation['id']);
        $copy = $this->service->duplicate($invitation);
        $this->created[] = (int) $copy['id'];

        $this->assertFalse('Duplicate: the copy is a new row', (int) $copy['id'] === (int) $invitation['id']);
        $this->assertSame('Duplicate: the copy starts as a draft', 'draft', (string) $copy['status']);
        $this->assertFalse('Duplicate: the copy has its own slug', (string) $copy['slug'] === (string) $invitation['slug']);
        $this->assertFalse(
            'Duplicate: the copy has its own short code',
            (string) $copy['short_code'] === (string) $invitation['short_code']
        );

        $original = (new \App\Repositories\InvitationDataRepository())->forInvitation((int) $invitation['id']);
        $copied = (new \App\Repositories\InvitationDataRepository())->forInvitation((int) $copy['id']);
        $this->assertSame('Duplicate: the content is copied', count($original), count($copied));
    }

    private function deletion(array $invitation): void
    {
        $db = Database::instance();
        $invitation = (array) $this->invitations->find((int) $invitation['id']);

        $this->service->delete($invitation);
        $this->assertTrue(
            'Delete: a soft-deleted invitation is gone from the owner listing',
            $this->invitations->findOwned((int) $invitation['id'], $this->userId) === null
        );
        $this->assertGreaterThan(
            'Delete: the row is retained with deleted_at set',
            0,
            (float) $db->value(
                'SELECT COUNT(*) FROM ' . $db->wrap($db->table('invitations'))
                . ' WHERE id = :id AND deleted_at IS NOT NULL',
                ['id' => (int) $invitation['id']],
                0
            )
        );

        $this->service->purge($invitation);
        $this->assertSame(
            'Purge: the row is removed for good',
            0,
            (int) $db->value(
                'SELECT COUNT(*) FROM ' . $db->wrap($db->table('invitations')) . ' WHERE id = :id',
                ['id' => (int) $invitation['id']],
                0
            )
        );
        $this->assertSame(
            'Purge: the content rows go with it',
            0,
            (int) $db->value(
                'SELECT COUNT(*) FROM ' . $db->wrap($db->table('invitation_data')) . ' WHERE invitation_id = :id',
                ['id' => (int) $invitation['id']],
                0
            )
        );
    }

    private function cleanUp(): void
    {
        $db = Database::instance();
        foreach (array_unique($this->created) as $id) {
            $invitation = $db->first(
                'SELECT * FROM ' . $db->wrap($db->table('invitations')) . ' WHERE id = :id',
                ['id' => $id]
            );
            if ($invitation !== null) {
                $this->service->purge((array) $this->invitations->find($id) ?: $invitation);
            }
        }
        if ($this->userId > 0) {
            $db->delete('users', ['id' => $this->userId]);
        }
    }
}
