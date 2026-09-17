<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Config;
use App\Core\Logger;
use App\Core\Url;
use App\Repositories\AuditRepository;
use App\Repositories\CronRepository;
use App\Repositories\HealthRepository;
use App\Repositories\InvitationRepository;
use App\Repositories\NotificationRepository;

/**
 * Scheduled maintenance tasks.
 *
 * Run from a real cron entry, or from the admin panel for hosts without one.
 * Every task is idempotent and records its own outcome, so a missed run
 * simply catches up on the next one.
 */
final class CronService
{
    public const TASKS = [
        'cleanup'     => 'Remove expired tokens, stale sessions and temporary files',
        'analytics'   => 'Aggregate analytics and prune raw event rows',
        'reminders'   => 'Notify owners about upcoming and finished events',
        'backup'      => 'Create the scheduled database backup',
        'health'      => 'Record a system health snapshot',
        'cache'       => 'Prune expired cache entries',
        'update-check' => 'Check GitHub for a new version',
    ];

    public function __construct(
        private readonly CronRepository $runs = new CronRepository()
    ) {
    }

    /** Secret used by the URL-triggered cron endpoint. */
    public function token(): string
    {
        $token = (string) SettingsService::instance()->get('cron_token', '');
        if ($token === '') {
            $token = bin2hex(random_bytes(20));
            SettingsService::instance()->set('cron_token', $token, 'string', 'system');
        }
        return $token;
    }

    public function verifyToken(?string $candidate): bool
    {
        $candidate = (string) $candidate;
        return $candidate !== '' && hash_equals($this->token(), $candidate);
    }

    /** The command an administrator should paste into cPanel or crontab. */
    public function instructions(): array
    {
        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        return [
            'cli' => $php . ' ' . ROOT_PATH . '/bin/console cron:run >> ' . STORAGE_PATH . '/logs/cron.log 2>&1',
            'url' => Url::to('cron/run', ['token' => $this->token()]),
            'crontab' => '0 2 * * * ' . $php . ' ' . ROOT_PATH . '/bin/console cron:run',
            'wget' => 'wget -q -O /dev/null "' . Url::to('cron/run', ['token' => $this->token()]) . '"',
        ];
    }

    /**
     * Run a task (or every task).
     *
     * @return array<string,array{ok:bool,message:string,duration_ms:int}>
     */
    public function run(string $task = 'all'): array
    {
        $tasks = $task === 'all' ? array_keys(self::TASKS) : [$task];
        $results = [];

        foreach ($tasks as $name) {
            if (!isset(self::TASKS[$name])) {
                $results[$name] = ['ok' => false, 'message' => 'Unknown task.', 'duration_ms' => 0];
                continue;
            }

            $started = microtime(true);
            try {
                $message = match ($name) {
                    'cleanup'      => $this->cleanup(),
                    'analytics'    => $this->aggregateAnalytics(),
                    'reminders'    => $this->sendReminders(),
                    'backup'       => $this->scheduledBackup(),
                    'health'       => $this->recordHealth(),
                    'cache'        => $this->pruneCache(),
                    'update-check' => $this->checkForUpdate(),
                    default        => 'Nothing to do.',
                };
                $duration = (int) round((microtime(true) - $started) * 1000);
                $this->runs->record($name, 'success', $message, $duration);
                $results[$name] = ['ok' => true, 'message' => $message, 'duration_ms' => $duration];
            } catch (\Throwable $e) {
                $duration = (int) round((microtime(true) - $started) * 1000);
                $this->runs->record($name, 'failed', $e->getMessage(), $duration);
                Logger::error('Cron task failed: ' . $name . ' - ' . $e->getMessage(), [], Logger::CRON);
                $results[$name] = ['ok' => false, 'message' => $e->getMessage(), 'duration_ms' => $duration];
            }
        }

        return $results;
    }

    // ------------------------------------------------------------------
    //  Tasks
    // ------------------------------------------------------------------

    private function cleanup(): string
    {
        $db = \App\Core\Database::instance();
        $parts = [];

        // Expired password reset tokens.
        $resets = $db->execute(
            'DELETE FROM ' . $db->wrap($db->table('password_resets')) . ' WHERE expires_at < :now',
            ['now' => time()]
        );
        $parts[] = $resets . ' expired reset token(s)';

        // Stale database sessions.
        if ($db->tableExists('sessions')) {
            $sessions = $db->execute(
                'DELETE FROM ' . $db->wrap($db->table('sessions')) . ' WHERE last_activity < :cutoff',
                ['cutoff' => time() - max(3600, (int) Config::get('session.lifetime', 7200) * 4)]
            );
            $parts[] = $sessions . ' stale session(s)';
        }

        // Spent and expired one-time login codes.
        if ($db->tableExists('auth_otp_codes')) {
            $codes = (new \App\Repositories\OtpRepository())->purgeExpired(2);
            $parts[] = $codes . ' old sign-in code(s)';
        }

        // Expired API tokens.
        if ($db->tableExists('api_tokens')) {
            $tokens = $db->execute(
                'DELETE FROM ' . $db->wrap($db->table('api_tokens'))
                . ' WHERE expires_at IS NOT NULL AND expires_at < :now',
                ['now' => \App\Core\Database::now()]
            );
            $parts[] = $tokens . ' expired API token(s)';
        }

        // Temporary files older than a day.
        $removedTemp = 0;
        foreach (glob(STORAGE_PATH . '/tmp/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < time() - 86400) {
                @unlink($file);
                $removedTemp++;
            }
        }
        $parts[] = $removedTemp . ' temporary file(s)';

        // Abandoned update staging directories.
        foreach (glob(STORAGE_PATH . '/update/staging-*') ?: [] as $directory) {
            if (is_dir($directory) && filemtime($directory) < time() - 86400) {
                ArchiveExtractor::deleteDirectory($directory);
            }
        }
        foreach (glob(STORAGE_PATH . '/update/source-*.zip') ?: [] as $file) {
            if (filemtime($file) < time() - 86400) {
                @unlink($file);
            }
        }

        // Old log files and notifications.
        $logs = Logger::purgeOlderThan(60);
        $parts[] = $logs . ' old log file(s)';
        $notifications = (new NotificationRepository())->purgeOlderThan(90);
        $parts[] = $notifications . ' read notification(s)';
        $audits = (new AuditRepository())->purgeOlderThan(365);
        $parts[] = $audits . ' old audit entr(ies)';
        (new HealthRepository())->purgeOlderThan(120);
        $this->runs->purgeOlderThan(60);

        return 'Removed ' . implode(', ', $parts) . '.';
    }

    private function aggregateAnalytics(): string
    {
        $analytics = new AnalyticsService();
        $pruned = $analytics->prune();
        $reconciled = $analytics->reconcile();
        return 'Pruned ' . $pruned['views'] . ' view, ' . $pruned['shares'] . ' share and '
            . $pruned['downloads'] . ' download rows; reconciled ' . $reconciled . ' invitation counter(s).';
    }

    private function sendReminders(): string
    {
        $invitations = new InvitationRepository();
        $notifications = new NotificationRepository();
        $db = $invitations->db();

        // Events happening tomorrow: remind the owner.
        $upcoming = $db->select(
            'SELECT id, user_id, title, slug, event_at FROM ' . $db->wrap($db->table('invitations')) . '
             WHERE deleted_at IS NULL AND status = :status
               AND event_at >= :from AND event_at < :to',
            [
                'status' => 'published',
                'from'   => date('Y-m-d 00:00:00', strtotime('+1 day')),
                'to'     => date('Y-m-d 00:00:00', strtotime('+2 days')),
            ]
        );

        $sent = 0;
        foreach ($upcoming as $invitation) {
            $key = 'reminder:tomorrow:' . $invitation['id'];
            if (Cache::has($key)) {
                continue;
            }
            $notifications->push(
                (int) $invitation['user_id'],
                'Tomorrow: ' . $invitation['title'],
                'Your celebration is tomorrow. Check your RSVP list so nobody is missed.',
                'reminder',
                Url::to('invitations/' . $invitation['id'] . '/rsvp'),
                'calendar'
            );
            Cache::put($key, true, 172800);
            $sent++;
        }

        // Events that finished a while ago: offer to archive.
        $finished = $invitations->expiredCandidates(30);
        $archived = 0;
        foreach ($finished as $invitation) {
            $key = 'reminder:finished:' . $invitation['id'];
            if (Cache::has($key)) {
                continue;
            }
            $notifications->push(
                (int) $invitation['user_id'],
                'Celebration finished: ' . $invitation['title'],
                'You can keep this invitation online as a memory, or archive it from My Invitations.',
                'info',
                Url::to('invitations'),
                'archive'
            );
            Cache::put($key, true, 2592000);
            $archived++;
        }

        return $sent . ' tomorrow reminder(s) and ' . $archived . ' post-event notice(s) sent.';
    }

    private function scheduledBackup(): string
    {
        if (!SettingsService::instance()->bool('backup_auto_enabled', true)) {
            return 'Automatic backups are disabled in settings.';
        }
        $result = (new BackupService())->create(BackupService::TYPE_DATABASE, 'Scheduled backup');
        if (!$result['ok']) {
            throw new \RuntimeException($result['message']);
        }
        return $result['message'];
    }

    private function recordHealth(): string
    {
        $result = (new HealthService())->run('cron');
        if ($result['status'] === HealthService::CRITICAL) {
            (new NotificationRepository())->notifyAdmins(
                'System health is critical',
                'The scheduled health check found ' . $result['summary'][HealthService::CRITICAL] . ' critical issue(s).',
                'error',
                Url::to('admin/system/health')
            );
        }
        return 'Health snapshot recorded: ' . $result['status'] . '.';
    }

    private function pruneCache(): string
    {
        $pruned = Cache::prune();
        return $pruned . ' expired cache entr(ies) removed.';
    }

    private function checkForUpdate(): string
    {
        $github = new GitHubService();
        if (!$github->isConfigured()) {
            return 'No update source configured.';
        }
        $check = (new UpdateService($github))->check();
        return $check['update_available']
            ? 'Update available: ' . $check['message']
            : 'Up to date.';
    }

    // ------------------------------------------------------------------
    //  Status
    // ------------------------------------------------------------------

    /** @return array<string,array<string,mixed>> */
    public function status(): array
    {
        $latest = $this->runs->latestPerTask();
        $out = [];
        foreach (self::TASKS as $name => $description) {
            $out[$name] = [
                'description' => $description,
                'last_run'    => $latest[$name] ?? null,
            ];
        }
        return $out;
    }

    public function recentRuns(int $limit = 30): array
    {
        return $this->runs->recent($limit);
    }
}
