<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Config;
use App\Core\Logger;
use App\Repositories\SettingRepository;

/**
 * Admin-editable settings, cached per request and in the file cache.
 *
 * A subset of keys is mapped onto the configuration tree so that changing,
 * say, the session lifetime in the admin panel actually takes effect.
 */
final class SettingsService
{
    private const CACHE_KEY = 'settings:map';

    private static ?self $instance = null;

    /** @var array<string,mixed>|null */
    private ?array $map = null;
    private ?SettingRepository $repository = null;

    /** setting key => configuration path */
    private const CONFIG_MAP = [
        'site_name'            => 'app.name',
        'site_tagline'         => 'app.tagline',
        'site_url'             => 'app.url',
        'default_locale'       => 'app.locale',
        'timezone'             => 'app.timezone',
        'session_lifetime'     => 'session.lifetime',
        'login_max_attempts'   => 'security.login_max_attempts',
        'login_decay_minutes'  => 'security.login_decay_minutes',
        'password_min_length'  => 'security.password_min_length',
        'api_rate_limit'       => 'security.api_rate_limit',
        'force_https'          => 'security.force_https',
        'csp_enabled'          => 'security.csp_enabled',
        'max_image_size'       => 'uploads.max_image_size',
        'max_music_size'       => 'uploads.max_music_size',
        'max_photos'           => 'uploads.max_photos',
        'pdf_engine'           => 'pdf.engine',
        'pdf_paper'            => 'pdf.paper',
        'mail_driver'          => 'mail.driver',
        'mail_from_address'    => 'mail.from_address',
        'mail_from_name'       => 'mail.from_name',
        'smtp_host'            => 'mail.smtp.host',
        'smtp_port'            => 'mail.smtp.port',
        'smtp_username'        => 'mail.smtp.username',
        'smtp_password'        => 'mail.smtp.password',
        'smtp_encryption'      => 'mail.smtp.encryption',
        'analytics_store_ip'   => 'analytics.store_ip',
        'update_keep_backups'  => 'update.keep_backups',
    ];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function repository(): SettingRepository
    {
        return $this->repository ??= new SettingRepository();
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }
        if (!Config::isInstalled()) {
            return $this->map = [];
        }
        $this->map = Cache::remember(self::CACHE_KEY, 900, function (): array {
            try {
                return $this->repository()->map();
            } catch (\Throwable $e) {
                Logger::warning('Could not read settings: ' . $e->getMessage());
                return [];
            }
        });
        return $this->map;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        $value = $all[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, null);
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, null);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, null);
        return is_array($value) ? $value : $default;
    }

    public function set(string $key, mixed $value, string $type = 'string', string $group = 'general', ?int $userId = null): void
    {
        $this->repository()->put($key, $value, $type, $group, $userId);
        $this->flush();
    }

    /** @param array<string,array{value:mixed,type?:string,group?:string}> $values */
    public function setMany(array $values, ?int $userId = null): void
    {
        foreach ($values as $key => $definition) {
            $this->repository()->put(
                $key,
                $definition['value'] ?? null,
                $definition['type'] ?? 'string',
                $definition['group'] ?? 'general',
                $userId
            );
        }
        $this->flush();
    }

    public function flush(): void
    {
        $this->map = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** Push stored settings into the live configuration tree. */
    public function applyToConfig(): void
    {
        $all = $this->all();
        foreach (self::CONFIG_MAP as $settingKey => $configPath) {
            if (!array_key_exists($settingKey, $all)) {
                continue;
            }
            $value = $all[$settingKey];
            if ($value === null || $value === '') {
                continue;
            }
            Config::set($configPath, $value);
        }
        // Keep the resolved locale list consistent with what is installed.
        $locales = $this->array('enabled_locales');
        if ($locales !== []) {
            $available = (array) Config::get('app.locales', []);
            $filtered = [];
            foreach ($locales as $code) {
                if (isset($available[$code])) {
                    $filtered[$code] = $available[$code];
                }
            }
            if ($filtered !== []) {
                Config::set('app.locales', $filtered);
            }
        }
    }

    /** Public, non-secret settings for the browser bundle. */
    public function publicMap(): array
    {
        try {
            return $this->repository()->publicMap();
        } catch (\Throwable) {
            return [];
        }
    }
}
