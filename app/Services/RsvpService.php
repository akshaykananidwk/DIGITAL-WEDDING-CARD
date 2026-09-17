<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Str;
use App\Core\Validator;
use App\Repositories\InvitationRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\RsvpRepository;

/**
 * Guest RSVP handling.
 *
 * The form is public, so it is rate limited per visitor and per IP, validated
 * strictly, and a repeat submission from the same visitor updates their
 * previous answer instead of creating a duplicate.
 */
final class RsvpService
{
    public function __construct(
        private readonly RsvpRepository $rsvp = new RsvpRepository(),
        private readonly InvitationRepository $invitations = new InvitationRepository(),
        private readonly AnalyticsService $analytics = new AnalyticsService()
    ) {
    }

    /**
     * Record a response.
     *
     * @param array<string,mixed> $input
     * @return array{ok:bool,message:string,errors:array<string,array<int,string>>,updated:bool}
     */
    public function submit(array $invitation, array $input): array
    {
        $invitationId = (int) $invitation['id'];

        if ((int) ($invitation['rsvp_enabled'] ?? 1) === 0) {
            return ['ok' => false, 'message' => 'RSVP is closed for this invitation.', 'errors' => [], 'updated' => false];
        }

        $visitorHash = $this->analytics->visitorHash($invitationId);

        // Two limits: a short one against double-tapping submit, and a
        // per-IP one against scripted abuse.
        if ($this->rsvp->recentFromVisitor($invitationId, $visitorHash, 20)) {
            return [
                'ok'      => false,
                'message' => 'We already received your response a moment ago.',
                'errors'  => [],
                'updated' => false,
            ];
        }
        $limit = RateLimiter::hit('rsvp', Request::clientIp(), 20, 3600);
        if (!$limit['allowed']) {
            Logger::security('RSVP rate limit hit', ['invitation_id' => $invitationId]);
            return [
                'ok'      => false,
                'message' => 'Too many submissions. Please try again later.',
                'errors'  => [],
                'updated' => false,
            ];
        }

        $validator = Validator::make($input, [
            'name'     => 'required|string|min:2|max:120|safe_text',
            'phone'    => 'nullable|phone',
            'email'    => 'nullable|email',
            'response' => 'required|in:yes,maybe,no',
            'guests'   => 'nullable|integer|min:0|max:50',
            'message'  => 'nullable|string|max:1000|safe_text',
        ], [
            'name'     => 'Your name',
            'phone'    => 'Mobile number',
            'response' => 'Your response',
            'guests'   => 'Number of guests',
            'message'  => 'Message',
        ]);

        if ($validator->fails()) {
            return [
                'ok'      => false,
                'message' => 'Please check the highlighted fields.',
                'errors'  => $validator->errors(),
                'updated' => false,
            ];
        }

        $data = $validator->validated();
        $response = (string) $data['response'];
        $guests = $response === 'no' ? 0 : max(0, (int) ($data['guests'] ?? 1));

        $row = [
            'invitation_id' => $invitationId,
            'name'          => mb_substr(trim((string) $data['name']), 0, 120),
            'phone'         => isset($data['phone']) ? Str::phone((string) $data['phone']) : null,
            'email'         => isset($data['email']) ? mb_strtolower(trim((string) $data['email'])) : null,
            'response'      => $response,
            'guests'        => $guests,
            'message'       => isset($data['message']) ? mb_substr(trim((string) $data['message']), 0, 1000) : null,
            'visitor_hash'  => $visitorHash,
            'ip_hash'       => Logger::clientIpHash(),
            'is_read'       => 0,
        ];

        $existing = $this->rsvp->findByVisitor($invitationId, $visitorHash);
        if ($existing !== null) {
            $this->rsvp->update((int) $existing['id'], $row);
            return [
                'ok'      => true,
                'message' => 'Thank you - your response has been updated.',
                'errors'  => [],
                'updated' => true,
            ];
        }

        $this->rsvp->create($row);
        $this->analytics->recordRsvp($invitation);
        $this->notifyOwner($invitation, $row);

        return [
            'ok'      => true,
            'message' => $this->thankYouMessage($response),
            'errors'  => [],
            'updated' => false,
        ];
    }

    private function thankYouMessage(string $response): string
    {
        return match ($response) {
            'yes'   => 'Thank you! We are delighted you will join us. 🎉',
            'maybe' => 'Thank you for letting us know. We hope you can make it!',
            default => 'Thank you for letting us know. You will be missed.',
        };
    }

    private function notifyOwner(array $invitation, array $row): void
    {
        try {
            (new NotificationRepository())->push(
                (int) $invitation['user_id'],
                'New RSVP: ' . $row['name'],
                ucfirst((string) $row['response']) . ' · ' . $row['guests'] . ' guest(s) for ' . $invitation['title'],
                'rsvp',
                \App\Core\Url::to('invitations/' . $invitation['id'] . '/rsvp'),
                'people'
            );
        } catch (\Throwable $e) {
            Logger::warning('Could not create the RSVP notification: ' . $e->getMessage());
        }
    }

    public function summary(int $invitationId): array
    {
        return $this->rsvp->summary($invitationId);
    }

    /** CSV export for the invitation owner. */
    public function toCsv(int $invitationId): string
    {
        $rows = $this->rsvp->allForInvitation($invitationId);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Name', 'Mobile', 'Email', 'Response', 'Guests', 'Message', 'Received']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['name'],
                $row['phone'],
                $row['email'],
                $row['response'],
                $row['guests'],
                $row['message'],
                $row['created_at'],
            ]);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);
        return $csv;
    }

    public function delete(int $rsvpId, int $invitationId): bool
    {
        $row = $this->rsvp->find($rsvpId);
        if ($row === null || (int) $row['invitation_id'] !== $invitationId) {
            return false;
        }
        $this->rsvp->forceDelete($rsvpId);
        $this->invitations->bumpCounter($invitationId, 'rsvp_count', -1);
        return true;
    }
}
