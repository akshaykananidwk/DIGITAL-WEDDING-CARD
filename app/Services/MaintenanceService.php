<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * File-flag maintenance mode.
 *
 * A file rather than a setting on purpose: it must work even when the
 * database is mid-migration or unreachable, which is exactly when an update
 * needs it.
 */
final class MaintenanceService
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function file(): string
    {
        return STORAGE_PATH . '/maintenance.json';
    }

    public function isActive(): bool
    {
        return is_file($this->file());
    }

    /** @return array<string,mixed> */
    public function state(): array
    {
        if (!$this->isActive()) {
            return [];
        }
        $decoded = json_decode((string) @file_get_contents($this->file()), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function enable(string $message = '', ?int $etaSeconds = null): string
    {
        $token = bin2hex(random_bytes(16));
        $payload = [
            'message'      => $message !== ''
                ? $message
                : 'We are applying a short upgrade. Your invitations are safe and will be back in a moment.',
            'started_at'   => date('c'),
            'eta'          => $etaSeconds,
            'bypass_token' => $token,
        ];
        $dir = dirname($this->file());
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($this->file(), json_encode($payload, JSON_PRETTY_PRINT), LOCK_EX);
        Logger::info('Maintenance mode enabled', ['message' => $payload['message']], Logger::UPDATE);
        return $token;
    }

    public function disable(): void
    {
        if (is_file($this->file())) {
            @unlink($this->file());
            Logger::info('Maintenance mode disabled', [], Logger::UPDATE);
        }
    }

    public function hasBypassToken(mixed $candidate): bool
    {
        if (!is_string($candidate) || $candidate === '') {
            return false;
        }
        $expected = (string) ($this->state()['bypass_token'] ?? '');
        return $expected !== '' && hash_equals($expected, $candidate);
    }
}
