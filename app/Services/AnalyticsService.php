<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Repositories\AnalyticsRepository;
use App\Repositories\InvitationRepository;

/**
 * Invitation analytics.
 *
 * Privacy first: no raw IP address, no cookie, no third-party script. A
 * visitor is identified by a salted hash of IP + user agent + invitation id,
 * which is enough to count unique viewers for a day and useless for anything
 * else.
 */
final class AnalyticsService
{
    public function __construct(
        private readonly AnalyticsRepository $analytics = new AnalyticsRepository(),
        private readonly InvitationRepository $invitations = new InvitationRepository()
    ) {
    }

    /** Record a page view. Never throws - analytics must not break a page. */
    public function recordView(array $invitation): void
    {
        try {
            $request = Request::instance();
            $device = $request->deviceType();

            // Link previews (WhatsApp, Facebook) are not guests.
            if ($device === 'bot') {
                return;
            }

            $isUnique = $this->analytics->recordView((int) $invitation['id'], [
                'visitor_hash'  => $this->visitorHash((int) $invitation['id']),
                'device_type'   => $device,
                'browser'       => $request->browser(),
                'referrer_host' => $this->referrerHost($request->referer()),
            ]);

            $this->invitations->bumpCounter((int) $invitation['id'], 'view_count');
            if ($isUnique) {
                $this->invitations->bumpCounter((int) $invitation['id'], 'unique_view_count');
            }
            $this->invitations->touchViewed((int) $invitation['id']);
        } catch (\Throwable $e) {
            Logger::warning('Failed to record a view: ' . $e->getMessage());
        }
    }

    public function recordShare(array $invitation, string $channel): void
    {
        if (!ShareService::isValidChannel($channel)) {
            $channel = 'other';
        }
        try {
            $this->analytics->recordShare(
                (int) $invitation['id'],
                $channel,
                $this->visitorHash((int) $invitation['id'])
            );
            $this->invitations->bumpCounter((int) $invitation['id'], 'share_count');
        } catch (\Throwable $e) {
            Logger::warning('Failed to record a share: ' . $e->getMessage());
        }
    }

    public function recordDownload(array $invitation, string $kind): void
    {
        $allowed = ['pdf', 'pdf_mobile', 'qr_png', 'qr_svg', 'ics', 'image'];
        if (!in_array($kind, $allowed, true)) {
            $kind = 'pdf';
        }
        try {
            $this->analytics->recordDownload(
                (int) $invitation['id'],
                $kind,
                $this->visitorHash((int) $invitation['id'])
            );
            $this->invitations->bumpCounter(
                (int) $invitation['id'],
                str_starts_with($kind, 'qr_') ? 'qr_scan_count' : 'download_count'
            );
        } catch (\Throwable $e) {
            Logger::warning('Failed to record a download: ' . $e->getMessage());
        }
    }

    public function recordRsvp(array $invitation): void
    {
        try {
            $this->analytics->recordRsvp((int) $invitation['id']);
            $this->invitations->bumpCounter((int) $invitation['id'], 'rsvp_count');
        } catch (\Throwable $e) {
            Logger::warning('Failed to record an RSVP: ' . $e->getMessage());
        }
    }

    /**
     * Everything the invitation analytics page needs, for one window.
     *
     * @param string $window today|7d|30d|all
     * @return array<string,mixed>
     */
    public function report(array $invitation, string $window = '30d'): array
    {
        $invitationId = (int) $invitation['id'];
        [$since, $days] = $this->windowBounds($window);

        return [
            'window'    => $window,
            'totals'    => $this->analytics->totals($invitationId, $since),
            'lifetime'  => $this->analytics->totals($invitationId, null),
            'series'    => $this->analytics->series($invitationId, max(1, $days)),
            'devices'   => $this->analytics->deviceBreakdown($invitationId, max(1, $days)),
            'browsers'  => $this->analytics->browserBreakdown($invitationId, max(1, $days)),
            'shares'    => $this->analytics->shareBreakdown($invitationId, max(1, $days)),
            'referrers' => $this->analytics->referrerBreakdown($invitationId, max(1, $days)),
            'rsvp'      => (new \App\Repositories\RsvpRepository())->summary($invitationId),
            'last_view' => $invitation['last_viewed_at'] ?? null,
        ];
    }

    /** @return array{0:string|null,1:int} since date and day count */
    public function windowBounds(string $window): array
    {
        return match ($window) {
            'today' => [date('Y-m-d'), 1],
            '7d'    => [date('Y-m-d', strtotime('-6 days')), 7],
            '90d'   => [date('Y-m-d', strtotime('-89 days')), 90],
            'all'   => [null, 90],
            default => [date('Y-m-d', strtotime('-29 days')), 30],
        };
    }

    /** Dashboard summary across everything a user owns. */
    public function userOverview(int $userId, int $days = 30): array
    {
        return [
            'stats'  => $this->invitations->dashboardStats($userId),
            'series' => $this->analytics->userSeries($userId, $days),
            'top'    => $this->analytics->topInvitations(5, $userId),
        ];
    }

    /** Platform summary for the admin dashboard. */
    public function platformOverview(int $days = 30): array
    {
        return [
            'series' => $this->analytics->platformSeries($days),
            'top'    => $this->analytics->topInvitations(10),
        ];
    }

    /**
     * A per-invitation visitor identifier that cannot be reversed into an IP.
     *
     * Salted with the application key and scoped to the invitation, so the
     * same hash never appears across two different invitations.
     */
    public function visitorHash(int $invitationId): string
    {
        $request = Request::instance();
        $material = Request::clientIp() . '|' . $request->userAgent() . '|' . $invitationId;
        return substr(hash_hmac('sha256', $material, (string) Config::get('app.key', 'inv')), 0, 32);
    }

    private function referrerHost(string $referer): ?string
    {
        if ($referer === '') {
            return null;
        }
        $host = parse_url($referer, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }
        return mb_substr(strtolower($host), 0, 120);
    }

    /** Nightly maintenance: prune raw rows, keeping the daily aggregates. */
    public function prune(): array
    {
        $days = (int) Config::get('analytics.aggregate_after', 90);
        return $this->analytics->aggregateAndPrune(max(7, $days));
    }

    public function reconcile(?int $invitationId = null): int
    {
        return $this->analytics->reconcileCounters($invitationId);
    }

    /** CSV export of the daily series (owner-facing). */
    public function toCsv(array $invitation, string $window = '30d'): string
    {
        $report = $this->report($invitation, $window);
        $rows = [['Date', 'Views', 'Unique views', 'Shares', 'Downloads']];
        foreach ($report['series']['labels'] as $index => $label) {
            $rows[] = [
                $label,
                $report['series']['views'][$index] ?? 0,
                $report['series']['unique_views'][$index] ?? 0,
                $report['series']['shares'][$index] ?? 0,
                $report['series']['downloads'][$index] ?? 0,
            ];
        }
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }
        // A UTF-8 BOM so Excel opens Gujarati text correctly.
        fwrite($handle, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);
        return $csv;
    }
}
