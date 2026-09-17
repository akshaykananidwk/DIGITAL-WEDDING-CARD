<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Repositories\AuditRepository;

/**
 * Admin audit trail.
 *
 * Every privileged action records who, what, when and (for updates) a diff of
 * the changed fields with secrets scrubbed.
 */
final class AuditService
{
    private static ?self $instance = null;
    private ?AuditRepository $repository = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function repository(): AuditRepository
    {
        return $this->repository ??= new AuditRepository();
    }

    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        string $description = '',
        array $changes = []
    ): void {
        if (!Config::isInstalled()) {
            return;
        }
        try {
            $user = Auth::user();
            $this->repository()->create([
                'user_id'     => is_array($user) ? (int) $user['id'] : null,
                'actor_name'  => is_array($user) ? (string) $user['name'] : 'system',
                'action'      => substr($action, 0, 80),
                'entity_type' => $entityType === null ? null : substr($entityType, 0, 60),
                'entity_id'   => $entityId,
                'description' => mb_substr($description, 0, 500),
                'changes'     => $changes === [] ? null : json_encode(
                    Logger::scrub($changes),
                    JSON_UNESCAPED_UNICODE
                ),
                'ip_hash'     => Logger::clientIpHash(),
                'user_agent'  => mb_substr(Request::instance()->userAgent(), 0, 255),
                'created_at'  => \App\Core\Database::now(),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the action it is recording.
            Logger::warning('Audit log write failed: ' . $e->getMessage());
        }
    }

    /**
     * Diff two record states and log only what actually changed.
     *
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     */
    public function logChanges(
        string $action,
        string $entityType,
        int $entityId,
        array $before,
        array $after,
        string $description = ''
    ): void {
        $diff = [];
        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;
            $normalisedOld = is_array($oldValue) ? json_encode($oldValue) : (string) $oldValue;
            $normalisedNew = is_array($newValue) ? json_encode($newValue) : (string) $newValue;
            if ($normalisedOld !== $normalisedNew) {
                $diff[$key] = ['from' => $oldValue, 'to' => $newValue];
            }
        }
        if ($diff === []) {
            return;
        }
        $this->log($action, $entityType, $entityId, $description, $diff);
    }

    public function security(string $action, string $description, array $context = []): void
    {
        Logger::security($description, $context + ['action' => $action]);
        $this->log($action, 'security', null, $description, $context);
    }
}
