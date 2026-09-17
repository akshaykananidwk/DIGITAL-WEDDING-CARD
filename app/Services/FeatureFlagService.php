<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Repositories\FeatureFlagRepository;

/**
 * Feature flags.
 *
 * Resolution order: database flag → config defaults → caller default. The
 * rollout percentage buckets users deterministically by id, so a user does
 * not flip in and out of a feature between requests.
 */
final class FeatureFlagService
{
    private static ?self $instance = null;
    private ?FeatureFlagRepository $repository = null;
    /** @var array<string,array<string,mixed>>|null */
    private ?array $flags = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function repository(): FeatureFlagRepository
    {
        return $this->repository ??= new FeatureFlagRepository();
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        if ($this->flags !== null) {
            return $this->flags;
        }
        if (!Config::isInstalled()) {
            return $this->flags = [];
        }
        try {
            $this->flags = $this->repository()->map();
        } catch (\Throwable) {
            $this->flags = [];
        }
        return $this->flags;
    }

    public function enabled(string $key, bool $default = false): bool
    {
        $flags = $this->all();
        if (isset($flags[$key])) {
            $flag = $flags[$key];
            if ((int) $flag['is_enabled'] !== 1) {
                return false;
            }
            $rollout = (int) $flag['rollout'];
            if ($rollout >= 100) {
                return true;
            }
            if ($rollout <= 0) {
                return false;
            }
            $userId = Auth::id();
            if ($userId === null) {
                return false;
            }
            return (crc32($key . ':' . $userId) % 100) < $rollout;
        }

        $configured = Config::get('features.' . $key);
        if (is_bool($configured)) {
            return $configured;
        }
        return $default;
    }

    public function disabled(string $key, bool $default = false): bool
    {
        return !$this->enabled($key, $default);
    }

    public function flush(): void
    {
        $this->flags = null;
        $this->repository()->flush();
    }
}
