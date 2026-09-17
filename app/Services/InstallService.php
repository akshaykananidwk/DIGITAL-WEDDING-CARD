<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Migrator;
use App\Core\Path;
use App\Core\Str;
use App\Core\Url;
use App\Core\Version;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Seeds\CategorySeeder;
use App\Seeds\DemoSeeder;
use App\Seeds\FontSeeder;
use App\Seeds\PageSeeder;
use App\Seeds\RoleSeeder;
use App\Seeds\SettingSeeder;
use App\Seeds\TemplateSeeder;

/**
 * One-click installer.
 *
 * No manual SQL import, no config file editing. The installer checks the
 * environment, tests the database credentials, creates the schema, seeds the
 * catalogue, creates the administrator, generates the application key and
 * writes the configuration - preferring a location outside the public web
 * root - then locks itself.
 */
final class InstallService
{
    /** PHP extensions without which the application cannot run. */
    private const REQUIRED_EXTENSIONS = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'fileinfo'];
    private const RECOMMENDED_EXTENSIONS = ['gd', 'zip', 'curl', 'intl', 'exif', 'zlib'];

    /** Directories the application must be able to write. */
    private const WRITABLE_PATHS = [
        'storage', 'storage/logs', 'storage/cache', 'storage/sessions',
        'storage/backups', 'storage/tmp', 'storage/update', 'uploads',
    ];

    // ------------------------------------------------------------------
    //  Step 1: requirements
    // ------------------------------------------------------------------

    /**
     * @return array{ok:bool,groups:array<string,array<int,array<string,mixed>>>,summary:array<string,int>}
     */
    public function requirements(): array
    {
        $groups = [
            'PHP'         => [],
            'Extensions'  => [],
            'Directories' => [],
            'Settings'    => [],
        ];

        $minimum = Version::minPhp();
        $groups['PHP'][] = $this->requirement(
            'PHP ' . $minimum . ' or newer',
            version_compare(PHP_VERSION, $minimum, '>='),
            'Detected PHP ' . PHP_VERSION,
            true
        );

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $groups['Extensions'][] = $this->requirement(
                $extension,
                extension_loaded($extension),
                extension_loaded($extension) ? 'Loaded' : 'Required - ask your host to enable it',
                true
            );
        }
        foreach (self::RECOMMENDED_EXTENSIONS as $extension) {
            $groups['Extensions'][] = $this->requirement(
                $extension . ' (recommended)',
                extension_loaded($extension),
                extension_loaded($extension)
                    ? 'Loaded'
                    : $this->extensionPurpose($extension),
                false
            );
        }

        foreach (self::WRITABLE_PATHS as $relative) {
            $absolute = ROOT_PATH . '/' . $relative;
            $writable = Path::makeDir($absolute) && Path::isWritable($absolute);
            $groups['Directories'][] = $this->requirement(
                $relative . '/',
                $writable,
                $writable ? 'Writable' : 'Set this directory to 755 and make sure the web server owns it',
                true
            );
        }

        // Where will the secrets go?
        $target = Config::preferredSecretTarget();
        $outside = !str_starts_with($target, ROOT_PATH . DIRECTORY_SEPARATOR);
        $groups['Directories'][] = $this->requirement(
            'Configuration location',
            true,
            $outside
                ? 'Secrets will be stored outside the web root (' . dirname($target) . ')'
                : 'Secrets will be stored in storage/config, protected by .htaccess',
            false
        );

        // A restriction the operator should know about: it decides where the
        // secrets and the backups can live, and it is the usual reason a fresh
        // deployment cannot reach anything above the document root.
        if (Path::isRestricted()) {
            $groups['Settings'][] = $this->requirement(
                'open_basedir',
                true,
                'In effect (' . implode(', ', Path::restrictions()) . '). '
                    . 'Secrets and backups stay inside storage/, which the web server is denied.',
                false
            );
        }

        $groups['Settings'][] = $this->requirement(
            'Memory limit',
            $this->memoryLimitBytes() === -1 || $this->memoryLimitBytes() >= 67108864,
            'Current: ' . (string) ini_get('memory_limit') . ' (64M or more recommended)',
            false
        );
        $groups['Settings'][] = $this->requirement(
            'Upload size',
            $this->bytes((string) ini_get('upload_max_filesize')) >= 4194304,
            'Current: ' . (string) ini_get('upload_max_filesize') . ' (8M recommended for photos)',
            false
        );
        $groups['Settings'][] = $this->requirement(
            'HTTPS',
            Url::isSecure(),
            Url::isSecure() ? 'This request is secure' : 'Install an SSL certificate before going live',
            false
        );

        $summary = ['passed' => 0, 'failed' => 0, 'warnings' => 0];
        $ok = true;
        foreach ($groups as $items) {
            foreach ($items as $item) {
                if ($item['passed']) {
                    $summary['passed']++;
                    continue;
                }
                if ($item['required']) {
                    $summary['failed']++;
                    $ok = false;
                } else {
                    $summary['warnings']++;
                }
            }
        }

        return ['ok' => $ok, 'groups' => $groups, 'summary' => $summary];
    }

    private function requirement(string $label, bool $passed, string $detail, bool $required): array
    {
        return compact('label', 'passed', 'detail', 'required');
    }

    private function extensionPurpose(string $extension): string
    {
        return match ($extension) {
            'gd'   => 'Needed for photo resizing and PNG QR codes',
            'zip'  => 'Needed for file backups and GitHub updates',
            'curl' => 'Needed for GitHub updates and the AI helper',
            'intl' => 'Improves slug transliteration for Gujarati titles',
            'exif' => 'Rotates phone photos correctly',
            'zlib' => 'Compresses PDFs and backups',
            default => 'Optional',
        };
    }

    // ------------------------------------------------------------------
    //  Step 2: database
    // ------------------------------------------------------------------

    /**
     * Test credentials, creating the database when permitted.
     *
     * @return array{ok:bool,message:string,version:string|null,created:bool}
     */
    public function testDatabase(array $credentials, bool $createIfMissing = true): array
    {
        $config = $this->normaliseCredentials($credentials);

        if ($config['database'] === '') {
            return ['ok' => false, 'message' => 'Please enter a database name.', 'version' => null, 'created' => false];
        }

        $database = Database::withConfig($config);
        $created = false;

        try {
            $database->pdo()->query('SELECT 1');
        } catch (\Throwable $e) {
            // The server may be reachable but the schema missing.
            if (!$createIfMissing || $config['driver'] !== 'mysql') {
                return [
                    'ok'      => false,
                    'message' => $this->databaseErrorHint($e->getMessage(), $config),
                    'version' => null,
                    'created' => false,
                ];
            }
            try {
                $server = $database->serverPdo();
                $name = $config['database'];
                if (preg_match('/^[A-Za-z0-9_\-]+$/', $name) !== 1) {
                    return [
                        'ok'      => false,
                        'message' => 'That database name contains characters that are not allowed.',
                        'version' => null,
                        'created' => false,
                    ];
                }
                $server->exec(
                    'CREATE DATABASE IF NOT EXISTS `' . $name . '` '
                    . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                );
                $created = true;
                $database = Database::withConfig($config);
                $database->pdo()->query('SELECT 1');
            } catch (\Throwable $inner) {
                return [
                    'ok'      => false,
                    'message' => $this->databaseErrorHint($inner->getMessage(), $config),
                    'version' => null,
                    'created' => false,
                ];
            }
        }

        // Check the version and that we can actually create a table.
        $version = $database->version();
        try {
            $database->pdo()->exec('CREATE TABLE IF NOT EXISTS _install_probe (id INT)');
            $database->pdo()->exec('DROP TABLE IF EXISTS _install_probe');
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => 'Connected, but this user cannot create tables. Grant it full privileges on the database.',
                'version' => $version,
                'created' => $created,
            ];
        }

        $existingTables = count($database->tables());

        return [
            'ok'      => true,
            'message' => 'Connected to ' . $version
                . ($created ? ' and created the database.' : '.')
                . ($existingTables > 0 ? ' Found ' . $existingTables . ' existing table(s).' : ''),
            'version' => $version,
            'created' => $created,
        ];
    }

    /** Turn a raw PDO message into something a non-developer can act on. */
    private function databaseErrorHint(string $message, array $config): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'access denied')) {
            return 'The database username or password was rejected. Check them in your hosting panel.';
        }
        if (str_contains($lower, 'unknown database')) {
            return 'The database "' . $config['database'] . '" does not exist and could not be created. '
                . 'Create it in your hosting panel first.';
        }
        if (str_contains($lower, "can't connect") || str_contains($lower, 'connection refused')) {
            return 'Could not reach the database server at ' . $config['host'] . ':' . $config['port']
                . '. On most shared hosts the correct value is "localhost".';
        }
        if (str_contains($lower, 'timed out')) {
            return 'The database connection timed out. Check the host name and any firewall.';
        }
        return 'Could not connect to the database. Please re-check the details.';
    }

    /** @return array<string,mixed> */
    private function normaliseCredentials(array $credentials): array
    {
        return [
            'driver'    => in_array($credentials['driver'] ?? 'mysql', ['mysql', 'sqlite'], true)
                ? (string) ($credentials['driver'] ?? 'mysql')
                : 'mysql',
            'host'      => trim((string) ($credentials['host'] ?? '127.0.0.1')) ?: '127.0.0.1',
            'port'      => (int) ($credentials['port'] ?? 3306) ?: 3306,
            'database'  => trim((string) ($credentials['database'] ?? '')),
            'username'  => (string) ($credentials['username'] ?? ''),
            'password'  => (string) ($credentials['password'] ?? ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => preg_replace('/[^A-Za-z0-9_]/', '', (string) ($credentials['prefix'] ?? '')) ?? '',
            'socket'    => trim((string) ($credentials['socket'] ?? '')) ?: null,
        ];
    }

    // ------------------------------------------------------------------
    //  Step 3: install
    // ------------------------------------------------------------------

    /**
     * Run the whole installation.
     *
     * @param array<string,mixed> $input
     * @return array{ok:bool,message:string,steps:array<int,array<string,mixed>>,admin_email:string|null}
     */
    public function install(array $input): array
    {
        $steps = [];
        $record = static function (string $label, bool $ok, string $detail) use (&$steps): void {
            $steps[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
        };

        if (Config::isInstalled()) {
            return [
                'ok'      => false,
                'message' => 'Application already installed.',
                'steps'   => [],
                'admin_email' => null,
            ];
        }

        $credentials = $this->normaliseCredentials([
            'driver'   => $input['db_driver'] ?? 'mysql',
            'host'     => $input['db_host'] ?? '127.0.0.1',
            'port'     => $input['db_port'] ?? 3306,
            'database' => $input['db_database'] ?? '',
            'username' => $input['db_username'] ?? '',
            'password' => $input['db_password'] ?? '',
            'prefix'   => $input['db_prefix'] ?? '',
            'socket'   => $input['db_socket'] ?? '',
        ]);

        // ---- Requirements ----
        $requirements = $this->requirements();
        if (!$requirements['ok']) {
            return [
                'ok'      => false,
                'message' => 'Some requirements are not met. Please fix them and reload this page.',
                'steps'   => [],
                'admin_email' => null,
            ];
        }
        $record('System requirements', true, $requirements['summary']['passed'] . ' checks passed.');

        // ---- Database ----
        $test = $this->testDatabase($credentials);
        if (!$test['ok']) {
            return ['ok' => false, 'message' => $test['message'], 'steps' => $steps, 'admin_email' => null];
        }
        $record('Database connection', true, (string) $test['message']);

        // ---- Configuration ----
        $appKey = Crypto::generateKey();
        $siteUrl = trim((string) ($input['site_url'] ?? '')) !== ''
            ? rtrim(trim((string) $input['site_url']), '/')
            : Url::detectBaseUrl();

        $secretData = [
            'app' => [
                'key'      => $appKey,
                'url'      => $siteUrl,
                'name'     => trim((string) ($input['site_name'] ?? '')) ?: 'Shubh Kankotri',
                'debug'    => false,
                'locale'   => in_array($input['locale'] ?? 'en', ['en', 'gu', 'hi'], true)
                    ? (string) $input['locale']
                    : 'en',
                'timezone' => 'Asia/Kolkata',
            ],
            'database' => $credentials,
            'installed_at' => gmdate('c'),
            'installed_version' => Version::current(),
        ];

        try {
            $secretPath = Config::writeSecretFile($secretData);
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => 'Could not write the configuration file: ' . $e->getMessage(),
                'steps'   => $steps,
                'admin_email' => null,
            ];
        }
        $record(
            'Configuration saved',
            true,
            str_starts_with($secretPath, ROOT_PATH . DIRECTORY_SEPARATOR)
                ? 'Written to storage/config (protected).'
                : 'Written outside the web root.'
        );

        // From here the application is configured, so use the real singleton.
        Database::reset();
        $db = Database::instance();

        // ---- Migrations ----
        try {
            $migrator = new Migrator($db);
            $result = $migrator->run();
            $record('Database tables created', true, count($result['ran']) . ' migration(s) applied.');
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'message' => 'The database tables could not be created: ' . $e->getMessage(),
                'steps'   => $steps,
                'admin_email' => null,
            ];
        }

        // ---- Seed reference data ----
        try {
            $roleSeeder = new RoleSeeder($db);
            $roleSeeder->run();
            $roleSeeder->seedPlans();
            $record('Roles and permissions', true, implode(' ', $roleSeeder->notes()));

            $settingSeeder = new SettingSeeder($db);
            $settingSeeder->run();
            $record('Default settings', true, implode(' ', $settingSeeder->notes()));

            $fontSeeder = new FontSeeder($db);
            $fontSeeder->run();
            $record('Fonts', true, implode(' ', $fontSeeder->notes()));

            $categorySeeder = new CategorySeeder($db);
            $categorySeeder->run();
            $record('Categories', true, implode(' ', $categorySeeder->notes()));

            $pageSeeder = new PageSeeder($db);
            $pageSeeder->run();
            $record('Content pages', true, implode(' ', $pageSeeder->notes()));
        } catch (\Throwable $e) {
            Logger::error('Installer seeding failed: ' . $e->getMessage());
            return [
                'ok'      => false,
                'message' => 'The starter data could not be created: ' . $e->getMessage(),
                'steps'   => $steps,
                'admin_email' => null,
            ];
        }

        // ---- Administrator ----
        $adminResult = $this->createAdministrator($input);
        if (!$adminResult['ok']) {
            return [
                'ok'      => false,
                'message' => $adminResult['message'],
                'steps'   => $steps,
                'admin_email' => null,
            ];
        }
        $adminId = (int) $adminResult['user_id'];
        $record('Administrator account', true, 'Created ' . $adminResult['email'] . '.');

        // ---- Templates ----
        try {
            $templateSeeder = new TemplateSeeder($db);
            $templateSeeder->run();
            $record('Template catalogue', true, implode(' ', $templateSeeder->notes()));

            $variantCount = max(0, min(5000, (int) ($input['template_count'] ?? 250)));
            if ($variantCount > 0) {
                $generated = (new TemplateGeneratorService())->generate($variantCount, true, $adminId);
                $record('Template variants', true, $generated['message']);
            }
        } catch (\Throwable $e) {
            // A partial catalogue is recoverable from the admin panel.
            Logger::warning('Template seeding incomplete: ' . $e->getMessage());
            $record('Template catalogue', false, 'Partially seeded: ' . $e->getMessage());
        }

        // ---- Demo content ----
        if (!empty($input['create_demo'])) {
            try {
                $demoSeeder = new DemoSeeder($adminId, $db);
                $demoSeeder->run();
                $record('Demo invitation', true, implode(' ', $demoSeeder->notes()));
            } catch (\Throwable $e) {
                Logger::warning('Demo seeding failed: ' . $e->getMessage());
                $record('Demo invitation', false, 'Skipped: ' . $e->getMessage());
            }
        }

        // ---- Finish ----
        SettingsService::instance()->setMany([
            'site_name'   => ['value' => $secretData['app']['name'], 'type' => 'string', 'group' => 'general'],
            'site_url'    => ['value' => $siteUrl, 'type' => 'string', 'group' => 'general'],
            'default_locale' => ['value' => $secretData['app']['locale'], 'type' => 'string', 'group' => 'general'],
            'app_version' => ['value' => Version::current(), 'type' => 'string', 'group' => 'system'],
            'installed_at' => ['value' => gmdate('c'), 'type' => 'string', 'group' => 'system'],
        ], $adminId);

        $this->writeLock($adminResult['email']);
        $record('Installation locked', true, 'The installer will refuse to run again.');

        $health = (new HealthService())->run('install');
        $record(
            'Health check',
            $health['status'] !== HealthService::CRITICAL,
            'Status: ' . $health['status'] . ' (' . $health['summary'][HealthService::OK] . ' passed).'
        );

        Logger::info('Installation completed', ['version' => Version::current()]);

        return [
            'ok'          => true,
            'message'     => 'Installation complete. You can sign in to the admin panel now.',
            'steps'       => $steps,
            'admin_email' => $adminResult['email'],
        ];
    }

    /** @return array{ok:bool,message:string,user_id:int|null,email:string} */
    private function createAdministrator(array $input): array
    {
        $name = trim((string) ($input['admin_name'] ?? ''));
        $email = strtolower(trim((string) ($input['admin_email'] ?? '')));
        $password = (string) ($input['admin_password'] ?? '');
        $confirmation = (string) ($input['admin_password_confirmation'] ?? '');

        if ($name === '' || mb_strlen($name) < 2) {
            return ['ok' => false, 'message' => 'Please enter the administrator name.', 'user_id' => null, 'email' => ''];
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'message' => 'Please enter a valid administrator email address.', 'user_id' => null, 'email' => ''];
        }
        if (mb_strlen($password) < 8) {
            return ['ok' => false, 'message' => 'The administrator password must be at least 8 characters.', 'user_id' => null, 'email' => ''];
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return [
                'ok' => false,
                'message' => 'The administrator password must contain at least one letter and one number.',
                'user_id' => null,
                'email' => '',
            ];
        }
        if (!hash_equals($password, $confirmation)) {
            return ['ok' => false, 'message' => 'The administrator passwords do not match.', 'user_id' => null, 'email' => ''];
        }

        $roles = new RoleRepository();
        $role = $roles->findBySlug('super-admin');
        if ($role === null) {
            return ['ok' => false, 'message' => 'The super-admin role is missing.', 'user_id' => null, 'email' => ''];
        }

        $users = new UserRepository();
        if ($users->emailExists($email)) {
            return ['ok' => false, 'message' => 'A user with that email address already exists.', 'user_id' => null, 'email' => ''];
        }

        $planId = Database::instance()->value(
            'SELECT id FROM ' . Database::instance()->wrap(Database::instance()->table('plans'))
            . ' WHERE is_default = 1 LIMIT 1'
        );

        $userId = $users->create([
            'name'              => mb_substr($name, 0, 120),
            'email'             => $email,
            'email_verified_at' => Database::now(),
            'phone'             => Str::phone((string) ($input['admin_phone'] ?? '')) ?: null,
            'password'          => Auth::hash($password),
            'role_id'           => (int) $role['id'],
            'plan_id'           => $planId === null ? null : (int) $planId,
            'status'            => 'active',
            'locale'            => in_array($input['locale'] ?? 'en', ['en', 'gu', 'hi'], true)
                ? (string) $input['locale']
                : 'en',
        ]);

        return ['ok' => true, 'message' => 'Administrator created.', 'user_id' => $userId, 'email' => $email];
    }

    // ------------------------------------------------------------------
    //  Lock
    // ------------------------------------------------------------------

    public function lockPath(): string
    {
        return STORAGE_PATH . '/installed.lock';
    }

    public function isLocked(): bool
    {
        return is_file($this->lockPath()) || Config::isInstalled();
    }

    private function writeLock(string $adminEmail): void
    {
        $payload = [
            'installed_at' => gmdate('c'),
            'version'      => Version::current(),
            'admin'        => $adminEmail,
            'php'          => PHP_VERSION,
        ];
        $directory = dirname($this->lockPath());
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }
        @file_put_contents($this->lockPath(), (string) json_encode($payload, JSON_PRETTY_PRINT));
        @chmod($this->lockPath(), 0640);
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    private function memoryLimitBytes(): int
    {
        $limit = (string) ini_get('memory_limit');
        return $limit === '-1' ? -1 : $this->bytes($limit);
    }

    private function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;
        return match ($unit) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** Sensible defaults for the installer form. */
    public function defaults(): array
    {
        return [
            'db_driver'      => 'mysql',
            'db_host'        => 'localhost',
            'db_port'        => 3306,
            'db_database'    => '',
            'db_username'    => '',
            'db_prefix'      => '',
            'site_name'      => 'Shubh Kankotri',
            'site_url'       => Url::detectBaseUrl(),
            'locale'         => 'en',
            'template_count' => 250,
            'create_demo'    => true,
        ];
    }
}
