<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Analytics reads and writes.
 *
 * Writes go to the raw event tables *and* bump the denormalised counters on
 * `invitations`, so a dashboard card never has to COUNT(*) a million rows.
 * Charts read from `invitation_daily_stats` for windows older than today.
 */
final class AnalyticsRepository extends BaseRepository
{
    protected string $table = 'invitation_views';
    protected bool $timestamps = false;

    private function dateExpr(string $column): string
    {
        return $this->db->isSqlite() ? "substr({$column},1,10)" : "DATE({$column})";
    }

    public function recordView(int $invitationId, array $context): bool
    {
        $isUnique = !$this->hasRecentView($invitationId, (string) $context['visitor_hash']);

        $this->db->insert('invitation_views', [
            'invitation_id' => $invitationId,
            'visitor_hash'  => $context['visitor_hash'],
            'device_type'   => $context['device_type'] ?? 'unknown',
            'browser'       => $context['browser'] ?? null,
            'referrer_host' => $context['referrer_host'] ?? null,
            'country'       => $context['country'] ?? null,
            'is_unique'     => $isUnique ? 1 : 0,
            'viewed_at'     => Database::now(),
        ]);

        $this->bumpDaily($invitationId, $isUnique ? ['views' => 1, 'unique_views' => 1] : ['views' => 1]);

        return $isUnique;
    }

    private function hasRecentView(int $invitationId, string $visitorHash): bool
    {
        $window = (int) \App\Core\Config::get('analytics.unique_window', 86400);
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('invitation_views')) . '
             WHERE invitation_id = :id AND visitor_hash = :hash AND viewed_at >= :since LIMIT 1',
            [
                'id'    => $invitationId,
                'hash'  => $visitorHash,
                'since' => date('Y-m-d H:i:s', time() - $window),
            ],
            0
        ) > 0;
    }

    public function recordShare(int $invitationId, string $channel, ?string $visitorHash = null): void
    {
        $this->db->insert('invitation_shares', [
            'invitation_id' => $invitationId,
            'channel'       => $channel,
            'visitor_hash'  => $visitorHash,
            'shared_at'     => Database::now(),
        ]);
        $this->bumpDaily($invitationId, ['shares' => 1]);
    }

    public function recordDownload(int $invitationId, string $kind, ?string $visitorHash = null): void
    {
        $this->db->insert('invitation_downloads', [
            'invitation_id' => $invitationId,
            'kind'          => $kind,
            'visitor_hash'  => $visitorHash,
            'downloaded_at' => Database::now(),
        ]);
        $isQr = str_starts_with($kind, 'qr_');
        $this->bumpDaily($invitationId, $isQr ? ['qr_scans' => 1] : ['downloads' => 1]);
    }

    public function recordRsvp(int $invitationId): void
    {
        $this->bumpDaily($invitationId, ['rsvps' => 1]);
    }

    /** Upsert today's aggregate row. */
    private function bumpDaily(int $invitationId, array $increments): void
    {
        $today = date('Y-m-d');
        $columns = ['views', 'unique_views', 'shares', 'downloads', 'qr_scans', 'rsvps'];

        $insert = [
            'invitation_id' => $invitationId,
            'stat_date'     => $today,
            'created_at'    => Database::now(),
            'updated_at'    => Database::now(),
        ];
        foreach ($columns as $column) {
            $insert[$column] = (int) ($increments[$column] ?? 0);
        }

        $sets = [];
        foreach (array_keys($increments) as $column) {
            if (!in_array($column, $columns, true)) {
                continue;
            }
            $wrapped = $this->db->wrap($column);
            $sets[] = $this->db->isSqlite()
                ? "{$wrapped} = {$wrapped} + excluded.{$wrapped}"
                : "{$wrapped} = {$wrapped} + VALUES({$wrapped})";
        }
        if ($sets === []) {
            return;
        }

        $table = $this->db->wrap($this->db->table('invitation_daily_stats'));
        $cols = array_keys($insert);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', array_map([$this->db, 'wrap'], $cols)) . ')'
            . ' VALUES (' . implode(', ', array_map(static fn ($c) => ':' . $c, $cols)) . ')';
        $sql .= $this->db->isSqlite()
            ? ' ON CONFLICT (invitation_id, stat_date) DO UPDATE SET ' . implode(', ', $sets)
            : ' ON DUPLICATE KEY UPDATE ' . implode(', ', $sets);

        $this->db->execute($sql, $insert);
    }

    /**
     * Totals for a window.
     *
     * @return array{views:int,unique_views:int,shares:int,downloads:int,qr_scans:int,rsvps:int}
     */
    public function totals(int $invitationId, ?string $since = null): array
    {
        $sql = 'SELECT COALESCE(SUM(views),0) AS views, COALESCE(SUM(unique_views),0) AS unique_views,
                       COALESCE(SUM(shares),0) AS shares, COALESCE(SUM(downloads),0) AS downloads,
                       COALESCE(SUM(qr_scans),0) AS qr_scans, COALESCE(SUM(rsvps),0) AS rsvps
                FROM ' . $this->db->wrap($this->db->table('invitation_daily_stats')) . '
                WHERE invitation_id = :id';
        $bindings = ['id' => $invitationId];
        if ($since !== null) {
            $sql .= ' AND stat_date >= :since';
            $bindings['since'] = $since;
        }
        $row = $this->db->first($sql, $bindings) ?? [];
        return [
            'views'        => (int) ($row['views'] ?? 0),
            'unique_views' => (int) ($row['unique_views'] ?? 0),
            'shares'       => (int) ($row['shares'] ?? 0),
            'downloads'    => (int) ($row['downloads'] ?? 0),
            'qr_scans'     => (int) ($row['qr_scans'] ?? 0),
            'rsvps'        => (int) ($row['rsvps'] ?? 0),
        ];
    }

    /**
     * Daily series padded with zeros so Chart.js draws a continuous line.
     *
     * @return array{labels:array<int,string>,views:array<int,int>,unique_views:array<int,int>,shares:array<int,int>,downloads:array<int,int>}
     */
    public function series(int $invitationId, int $days = 30): array
    {
        $start = new \DateTimeImmutable('-' . max(0, $days - 1) . ' days');
        $rows = $this->db->select(
            'SELECT stat_date, views, unique_views, shares, downloads, qr_scans, rsvps
             FROM ' . $this->db->wrap($this->db->table('invitation_daily_stats')) . '
             WHERE invitation_id = :id AND stat_date >= :since
             ORDER BY stat_date ASC',
            ['id' => $invitationId, 'since' => $start->format('Y-m-d')]
        );
        $byDate = [];
        foreach ($rows as $row) {
            $byDate[substr((string) $row['stat_date'], 0, 10)] = $row;
        }

        $labels = $views = $unique = $shares = $downloads = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->modify('+' . $i . ' days')->format('Y-m-d');
            $labels[] = $date;
            $views[] = (int) ($byDate[$date]['views'] ?? 0);
            $unique[] = (int) ($byDate[$date]['unique_views'] ?? 0);
            $shares[] = (int) ($byDate[$date]['shares'] ?? 0);
            $downloads[] = (int) ($byDate[$date]['downloads'] ?? 0);
        }

        return [
            'labels'       => $labels,
            'views'        => $views,
            'unique_views' => $unique,
            'shares'       => $shares,
            'downloads'    => $downloads,
        ];
    }

    /** @return array<string,int> device type => views */
    public function deviceBreakdown(int $invitationId, int $days = 30): array
    {
        return array_map('intval', $this->db->pairs(
            'SELECT device_type, COUNT(*) FROM ' . $this->db->wrap($this->db->table('invitation_views')) . '
             WHERE invitation_id = :id AND viewed_at >= :since
             GROUP BY device_type ORDER BY COUNT(*) DESC',
            ['id' => $invitationId, 'since' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]
        ));
    }

    /** @return array<string,int> browser => views */
    public function browserBreakdown(int $invitationId, int $days = 30): array
    {
        return array_map('intval', $this->db->pairs(
            'SELECT COALESCE(browser, :unknown), COUNT(*) FROM ' . $this->db->wrap($this->db->table('invitation_views')) . '
             WHERE invitation_id = :id AND viewed_at >= :since
             GROUP BY browser ORDER BY COUNT(*) DESC LIMIT 8',
            [
                'unknown' => 'Other',
                'id'      => $invitationId,
                'since'   => date('Y-m-d H:i:s', strtotime('-' . $days . ' days')),
            ]
        ));
    }

    /** @return array<string,int> channel => shares */
    public function shareBreakdown(int $invitationId, int $days = 30): array
    {
        return array_map('intval', $this->db->pairs(
            'SELECT channel, COUNT(*) FROM ' . $this->db->wrap($this->db->table('invitation_shares')) . '
             WHERE invitation_id = :id AND shared_at >= :since
             GROUP BY channel ORDER BY COUNT(*) DESC',
            ['id' => $invitationId, 'since' => date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]
        ));
    }

    /** @return array<string,int> referrer host => views */
    public function referrerBreakdown(int $invitationId, int $days = 30): array
    {
        return array_map('intval', $this->db->pairs(
            'SELECT COALESCE(NULLIF(referrer_host, :empty), :direct), COUNT(*)
             FROM ' . $this->db->wrap($this->db->table('invitation_views')) . '
             WHERE invitation_id = :id AND viewed_at >= :since
             GROUP BY referrer_host ORDER BY COUNT(*) DESC LIMIT 8',
            [
                'empty'  => '',
                'direct' => 'Direct',
                'id'     => $invitationId,
                'since'  => date('Y-m-d H:i:s', strtotime('-' . $days . ' days')),
            ]
        ));
    }

    /** Aggregate across everything the user owns (dashboard chart). */
    public function userSeries(int $userId, int $days = 30): array
    {
        $start = new \DateTimeImmutable('-' . max(0, $days - 1) . ' days');
        $rows = $this->db->select(
            'SELECT s.stat_date, SUM(s.views) AS views, SUM(s.unique_views) AS unique_views,
                    SUM(s.shares) AS shares, SUM(s.downloads) AS downloads
             FROM ' . $this->db->wrap($this->db->table('invitation_daily_stats')) . ' s
             JOIN ' . $this->db->wrap($this->db->table('invitations')) . ' i ON i.id = s.invitation_id
             WHERE i.user_id = :user AND i.deleted_at IS NULL AND s.stat_date >= :since
             GROUP BY s.stat_date ORDER BY s.stat_date ASC',
            ['user' => $userId, 'since' => $start->format('Y-m-d')]
        );
        $byDate = [];
        foreach ($rows as $row) {
            $byDate[substr((string) $row['stat_date'], 0, 10)] = $row;
        }
        $labels = $views = $unique = $shares = $downloads = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->modify('+' . $i . ' days')->format('Y-m-d');
            $labels[] = $date;
            $views[] = (int) ($byDate[$date]['views'] ?? 0);
            $unique[] = (int) ($byDate[$date]['unique_views'] ?? 0);
            $shares[] = (int) ($byDate[$date]['shares'] ?? 0);
            $downloads[] = (int) ($byDate[$date]['downloads'] ?? 0);
        }
        return compact('labels', 'views', 'unique', 'shares', 'downloads');
    }

    /** Platform-wide series for the admin dashboard. */
    public function platformSeries(int $days = 30): array
    {
        $start = new \DateTimeImmutable('-' . max(0, $days - 1) . ' days');
        $rows = $this->db->select(
            'SELECT stat_date, SUM(views) AS views, SUM(unique_views) AS unique_views,
                    SUM(shares) AS shares, SUM(downloads) AS downloads, SUM(rsvps) AS rsvps
             FROM ' . $this->db->wrap($this->db->table('invitation_daily_stats')) . '
             WHERE stat_date >= :since GROUP BY stat_date ORDER BY stat_date ASC',
            ['since' => $start->format('Y-m-d')]
        );
        $byDate = [];
        foreach ($rows as $row) {
            $byDate[substr((string) $row['stat_date'], 0, 10)] = $row;
        }
        $labels = $views = $unique = $shares = $downloads = $rsvps = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->modify('+' . $i . ' days')->format('Y-m-d');
            $labels[] = $date;
            $views[] = (int) ($byDate[$date]['views'] ?? 0);
            $unique[] = (int) ($byDate[$date]['unique_views'] ?? 0);
            $shares[] = (int) ($byDate[$date]['shares'] ?? 0);
            $downloads[] = (int) ($byDate[$date]['downloads'] ?? 0);
            $rsvps[] = (int) ($byDate[$date]['rsvps'] ?? 0);
        }
        return compact('labels', 'views', 'unique', 'shares', 'downloads', 'rsvps');
    }

    /** @return array<int,array<string,mixed>> top invitations by views */
    public function topInvitations(int $limit = 10, ?int $userId = null): array
    {
        $sql = 'SELECT i.id, i.title, i.slug, i.view_count, i.share_count, i.rsvp_count
                FROM ' . $this->db->wrap($this->db->table('invitations')) . ' i
                WHERE i.deleted_at IS NULL';
        $bindings = [];
        if ($userId !== null) {
            $sql .= ' AND i.user_id = :user';
            $bindings['user'] = $userId;
        }
        $sql .= ' ORDER BY i.view_count DESC LIMIT ' . max(1, min(50, $limit));
        return $this->db->select($sql, $bindings);
    }

    /**
     * Roll raw event rows older than `days` into the daily table and delete
     * them. Keeps the hot tables small without losing the charts.
     *
     * @return array{views:int,shares:int,downloads:int}
     */
    public function aggregateAndPrune(int $days): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        // Daily rows already hold the numbers, so pruning is purely deletion.
        $views = $this->db->execute(
            'DELETE FROM ' . $this->db->wrap($this->db->table('invitation_views')) . ' WHERE viewed_at < :cutoff',
            ['cutoff' => $cutoff]
        );
        $shares = $this->db->execute(
            'DELETE FROM ' . $this->db->wrap($this->db->table('invitation_shares')) . ' WHERE shared_at < :cutoff',
            ['cutoff' => $cutoff]
        );
        $downloads = $this->db->execute(
            'DELETE FROM ' . $this->db->wrap($this->db->table('invitation_downloads')) . ' WHERE downloaded_at < :cutoff',
            ['cutoff' => $cutoff]
        );
        return ['views' => $views, 'shares' => $shares, 'downloads' => $downloads];
    }

    /** Recompute the counters on `invitations` from the daily aggregates. */
    public function reconcileCounters(?int $invitationId = null): int
    {
        $daily = $this->db->wrap($this->db->table('invitation_daily_stats'));
        $invitations = $this->db->wrap($this->db->table('invitations'));
        $sql = 'UPDATE ' . $invitations . ' i SET
                    view_count        = COALESCE((SELECT SUM(views) FROM ' . $daily . ' d WHERE d.invitation_id = i.id), 0),
                    unique_view_count = COALESCE((SELECT SUM(unique_views) FROM ' . $daily . ' d WHERE d.invitation_id = i.id), 0),
                    share_count       = COALESCE((SELECT SUM(shares) FROM ' . $daily . ' d WHERE d.invitation_id = i.id), 0),
                    download_count    = COALESCE((SELECT SUM(downloads) FROM ' . $daily . ' d WHERE d.invitation_id = i.id), 0),
                    qr_scan_count     = COALESCE((SELECT SUM(qr_scans) FROM ' . $daily . ' d WHERE d.invitation_id = i.id), 0)
                WHERE i.deleted_at IS NULL';
        $bindings = [];
        if ($invitationId !== null) {
            $sql .= ' AND i.id = :id';
            $bindings['id'] = $invitationId;
        }
        return $this->db->execute($sql, $bindings);
    }
}
