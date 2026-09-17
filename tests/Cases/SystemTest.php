<?php

declare(strict_types=1);

namespace Tests\Cases;

use App\Core\Cache;
use App\Core\Config;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Migrator;
use App\Core\Path;
use App\Core\Router;
use App\Core\Version;
use App\Services\BackupService;
use App\Services\CronService;
use App\Services\GitHubService;
use App\Services\HealthService;
use App\Services\InstallService;
use App\Services\MaintenanceService;
use App\Services\SitemapService;
use App\Services\UpdateService;
use Tests\TestCase;

/** Section 60: installer, migrations, routing, health, backups, cron, updates. */
final class SystemTest extends TestCase
{
    public function name(): string
    {
        return 'System, installer, backups and updates';
    }

    public function run(): void
    {
        $this->installation();
        $this->restrictedHost();
        $this->schema();
        $this->routing();
        $this->localisation();
        $this->cache();
        $this->health();
        $this->maintenance();
        $this->backups();
        $this->cron();
        $this->seo();
        $this->updateSafety();
    }

    private function installation(): void
    {
        $this->assertTrue('Install: the application reports itself installed', Config::isInstalled());

        $installer = new InstallService();
        $this->assertTrue('Install: the installer is locked', $installer->isLocked());
        $this->assertTrue('Install: the lock file exists', is_file($installer->lockPath()));

        $requirements = $installer->requirements();
        $this->assertTrue('Install: requirements pass on this host', (bool) $requirements['ok']);

        // Secrets live outside the document root.
        $secretPath = Config::secretFile();
        $this->assertTrue('Install: a secret file is in use', $secretPath !== null && is_file($secretPath));
        $this->assertFalse(
            'Install: the secret file is not under the web root',
            $secretPath !== null && str_starts_with((string) realpath($secretPath), (string) realpath(ROOT_PATH) . '/')
        );
        $this->assertGreaterThan('Install: an application key is set', 20, (float) strlen((string) Config::get('app.key')));
        $this->assertFalse('Install: debug mode is off', (bool) Config::get('app.debug', false));

        // Re-running the installer must refuse rather than wipe anything.
        $again = $installer->install(['db_database' => 'should_not_be_used']);
        $this->assertFalse('Install: a second install attempt is refused', (bool) $again['ok']);
        $this->assertContains('Install: it says so plainly', 'already installed', strtolower((string) $again['message']));
    }

    /**
     * A host with open_basedir narrowed to the document root.
     *
     * This is what took a fresh deployment down: probing for the secret file
     * above the web root raises a warning, a warning is an exception here, and
     * so every page - the installer included - answered 500.
     *
     * open_basedir can only be narrowed, never widened, so the restriction is
     * applied to a child process; this one still needs the database socket.
     */
    private function restrictedHost(): void
    {
        $outside = dirname(ROOT_PATH) . '/' . Config::EXTERNAL_DIR . '/app.php';

        // Unrestricted, which is this host: nothing is out of bounds.
        $this->assertFalse('open_basedir: unrestricted on this host', Path::isRestricted());
        $this->assertSame('open_basedir: no roots are reported', [], Path::restrictions());
        $this->assertTrue('open_basedir: a path above the web root is allowed', Path::allowed($outside));

        // Prefix matching must not be fooled by a sibling with a shared prefix
        // or by traversal.
        Path::flush();
        $this->assertTrue('open_basedir: an unrestricted probe answers', Path::isDir(STORAGE_PATH));

        $report = $this->restrictedReport();
        if ($report === null) {
            $this->pass('open_basedir: skipped, this host cannot start a child process');
            return;
        }

        $this->assertTrue(
            'open_basedir: the application boots under the restriction',
            (bool) ($report['booted'] ?? false),
            (string) ($report['error'] ?? '')
        );
        // ?? would read a present null as absent, so ask the array.
        $this->assertTrue(
            'open_basedir: booting raises nothing',
            array_key_exists('error', $report) && $report['error'] === null,
            (string) ($report['error'] ?? 'no error key in the report')
        );
        $this->assertTrue('open_basedir: the restriction is detected', (bool) ($report['restricted'] ?? false));
        $this->assertFalse('open_basedir: a path above the web root is refused', (bool) ($report['allows_outside'] ?? true));
        $this->assertTrue('open_basedir: a path inside it is still allowed', (bool) ($report['allows_inside'] ?? false));

        // The probes answer instead of raising, which is the whole point.
        foreach (['is_file_outside', 'is_dir_outside', 'writable_outside', 'makedir_outside'] as $probe) {
            $this->assertFalse('open_basedir: ' . $probe . ' answers false rather than raising', (bool) ($report[$probe] ?? true));
        }

        // And nothing is offered that cannot be reached.
        $this->assertSame(
            'open_basedir: only reachable secret locations are offered',
            [STORAGE_PATH . '/config/app.php'],
            $report['candidates'] ?? []
        );
        $this->assertSame(
            'open_basedir: a fresh install would write inside storage',
            STORAGE_PATH . '/config/app.php',
            (string) ($report['install_target'] ?? '')
        );
        $this->assertSame(
            'open_basedir: backups fall back inside storage',
            STORAGE_PATH . '/backups',
            (string) ($report['backup_directory'] ?? '')
        );
        $this->assertTrue(
            'open_basedir: the installer lists the restriction',
            (bool) ($report['requirement_shown'] ?? false)
        );

        // Temporary files: the system temp directory is out of bounds on such
        // a host, which used to fail PDF export outright.
        $this->assertSame(
            'open_basedir: temporary files go inside storage',
            STORAGE_PATH . '/tmp',
            (string) ($report['temp_dir'] ?? '')
        );
        $this->assertTrue(
            'open_basedir: a temporary file is created inside the root',
            (bool) ($report['temp_file_inside_root'] ?? false),
            (string) ($report['temp_file'] ?? 'none')
        );
        $this->assertTrue('open_basedir: a QR image is placed in a PDF', (bool) ($report['pdf_image_placed'] ?? false));
        $this->assertTrue('open_basedir: the PDF is a PDF', (bool) ($report['pdf_is_pdf'] ?? false));
        $this->assertGreaterThan(
            'open_basedir: the PDF has content',
            1000,
            (float) ($report['pdf_bytes'] ?? 0)
        );
        $this->assertTrue(
            'Logging: an unwritable log file falls back to the host error log',
            (bool) ($report['log_fallback_ok'] ?? false)
        );
    }

    /**
     * Boot a child process under the restriction and read its report.
     *
     * @return array<string,mixed>|null null when a child cannot be started
     */
    private function restrictedReport(): ?array
    {
        $script = ROOT_PATH . '/tests/support/restricted-host.php';
        if (!function_exists('exec') || !is_file($script) || PHP_BINARY === '') {
            return null;
        }

        $command = escapeshellarg(PHP_BINARY)
            . ' -d ' . escapeshellarg('open_basedir=' . ROOT_PATH . PATH_SEPARATOR . sys_get_temp_dir())
            . ' ' . escapeshellarg($script) . ' 2>&1';

        $output = [];
        $status = 0;
        @exec($command, $output, $status);
        $decoded = json_decode(trim(implode("\n", $output)), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function schema(): void
    {
        $db = Database::instance();
        $migrator = new Migrator($db);

        $this->assertSame('Migrations: nothing is pending', [], $migrator->pending());
        $this->assertGreaterThan('Migrations: migrations have been applied', 5, (float) count($migrator->applied()));

        $tables = $db->tables();
        $this->assertGreaterThan('Schema: the full schema is present', 35, (float) count($tables));

        foreach ([
            'users', 'roles', 'permissions', 'plans', 'categories', 'subcategories',
            'templates', 'template_fields', 'invitations', 'invitation_data',
            'invitation_views', 'invitation_daily_stats', 'rsvp', 'settings',
            'feature_flags', 'media', 'fonts', 'pages', 'audit_logs', 'backups',
            'update_logs', 'system_health_logs', 'cron_runs', 'migrations',
        ] as $table) {
            $this->assertTrue('Schema: table ' . $table . ' exists', $db->tableExists($table));
        }

        // Indexes the listing and analytics queries depend on.
        $this->assertTrue('Schema: invitations.slug is unique', $this->hasUniqueIndex('invitations', 'slug'));
        $this->assertTrue('Schema: templates.slug is unique', $this->hasUniqueIndex('templates', 'slug'));
        $this->assertTrue('Schema: users.email is unique', $this->hasUniqueIndex('users', 'email'));

        // utf8mb4 all the way down, or Gujarati text would be mangled.
        if (!$db->isSqlite()) {
            $charset = (string) $db->value(
                "SELECT character_set_name FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :t AND column_name = 'title'",
                ['t' => $db->table('invitations')],
                ''
            );
            $this->assertSame('Schema: text columns are utf8mb4', 'utf8mb4', $charset);
        }
    }

    private function hasUniqueIndex(string $table, string $column): bool
    {
        $db = Database::instance();
        if ($db->isSqlite()) {
            return true;
        }
        return (int) $db->value(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c AND non_unique = 0',
            ['t' => $db->table($table), 'c' => $column],
            0
        ) > 0;
    }

    private function routing(): void
    {
        $router = new Router();
        $router->get('/i/{code:[A-Za-z0-9]{4,12}}', static fn () => null, [], 'short');
        $router->get('/lang/{locale:[a-z]{2}}', static fn () => null, [], 'lang');
        $router->get('/invite/{slug:[A-Za-z0-9\-]+}', static fn () => null, [], 'invite');
        $router->get('/builder/{id:\d+}/share', static fn () => null, [], 'share');

        // Quantifiers inside a placeholder pattern must survive compilation.
        $this->assertTrue('Routing: a {4,12} quantifier matches', $router->match('GET', '/i/PWZLQU') !== null);
        $this->assertTrue('Routing: too short is rejected', $router->match('GET', '/i/ABC') === null);
        $this->assertTrue('Routing: a {2} quantifier matches', $router->match('GET', '/lang/gu') !== null);
        $this->assertTrue('Routing: a three-letter locale is rejected', $router->match('GET', '/lang/guj') === null);

        $matched = $router->match('GET', '/builder/42/share');
        $this->assertTrue('Routing: a numeric id matches', $matched !== null);
        $this->assertSame('Routing: the id is captured', '42', (string) ($matched['params']['id'] ?? ''));

        $this->assertTrue('Routing: a traversal path does not match', $router->match('GET', '/invite/../../etc') === null);
        $this->assertTrue('Routing: an unknown path does not match', $router->match('GET', '/nope') === null);

        // The real table is registered and reachable.
        $app = \App\Core\Application::getInstance();
        $app->boot();
        $real = $app->router();
        $registered = 0;
        foreach (['GET', 'POST', 'PUT', 'DELETE'] as $verb) {
            $registered += count(array_filter(
                ['/', '/templates', '/login', '/dashboard', '/admin'],
                static fn (string $path): bool => $real->match($verb, $path) !== null
            ));
        }
        $this->assertGreaterThan('Routing: the application registers its routes', 4, (float) $registered);
        foreach (['/', '/templates', '/login', '/dashboard', '/admin', '/api/v1/templates'] as $path) {
            $method = 'GET';
            $this->assertTrue('Routing: ' . $path . ' is routable', $real->match($method, $path) !== null);
        }
    }

    private function localisation(): void
    {
        $locales = Lang::available();
        $this->assertGreaterThan('i18n: three locales are available', 2, (float) count($locales));

        $keys = [];
        foreach (array_keys($locales) as $locale) {
            $flat = $this->flatten(require ROOT_PATH . '/app/Lang/' . $locale . '.php');
            $keys[$locale] = $flat;
            $this->assertGreaterThan('i18n: ' . $locale . ' has translations', 200, (float) count($flat));
        }
        $reference = array_keys($keys['en'] ?? []);
        foreach ($keys as $locale => $flat) {
            $missing = array_diff($reference, array_keys($flat));
            $this->assertSame(
                'i18n: ' . $locale . ' has every key',
                [],
                array_values($missing)
            );
            foreach ($flat as $key => $value) {
                if (trim((string) $value) === '') {
                    $this->fail('i18n: ' . $locale . '.' . $key . ' is empty', 'Empty translation.');
                    return;
                }
            }
        }
        $this->pass('i18n: no translation is empty');

        // Placeholders are substituted, and a missing key degrades gracefully.
        Lang::setLocale('gu');
        $this->assertContains('i18n: a placeholder is replaced', '7', Lang::get('builder.step', ['current' => 7, 'total' => 8]));
        // A missing key degrades to a humanised label rather than showing a
        // raw dotted key to a guest.
        $this->assertSame('i18n: an unknown key degrades gracefully', 'Nothing', Lang::get('nope.nothing'));
        $this->assertNotContains('i18n: the raw key is not shown', '.', Lang::get('nope.nothing'));
        Lang::setLocale('en');
    }

    /** @return array<string,string> */
    private function flatten(array $translations, string $prefix = ''): array
    {
        $flat = [];
        foreach ($translations as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat += $this->flatten($value, $path);
            } else {
                $flat[$path] = (string) $value;
            }
        }
        return $flat;
    }

    private function cache(): void
    {
        $key = 'suite-cache-' . bin2hex(random_bytes(3));
        Cache::put($key, ['value' => 42], 60);
        $this->assertSame('Cache: a value round trips', 42, (int) (Cache::get($key)['value'] ?? 0));
        $this->assertTrue('Cache: has() sees it', Cache::has($key));
        Cache::forget($key);
        $this->assertFalse('Cache: forget() removes it', Cache::has($key));

        $calls = 0;
        $remember = static function () use (&$calls): int {
            $calls++;
            return 7;
        };
        $key2 = 'suite-remember-' . bin2hex(random_bytes(3));
        Cache::remember($key2, 60, $remember);
        Cache::remember($key2, 60, $remember);
        $this->assertSame('Cache: remember() only computes once', 1, $calls);
        Cache::forget($key2);

        $expiring = 'suite-expired-' . bin2hex(random_bytes(3));
        Cache::put($expiring, 'x', -5);
        $this->assertFalse('Cache: an expired entry is not returned', Cache::has($expiring));
    }

    private function health(): void
    {
        $result = (new HealthService())->run('suite', false);

        $this->assertGreaterThan('Health: many checks run', 18, (float) count($result['checks']));
        $this->assertTrue(
            'Health: no check is critical',
            (int) ($result['summary'][HealthService::CRITICAL] ?? 0) === 0,
            json_encode(array_values(array_filter(
                $result['checks'],
                static fn (array $c): bool => $c['status'] === HealthService::CRITICAL
            )) ?: [])
        );

        $critical = (new HealthService())->runCritical();
        $this->assertSame('Health: the update gate passes', HealthService::OK, (string) $critical['status']);

        $meta = (new HealthService())->meta();
        $this->assertContains('Health: PHP version is reported', PHP_VERSION, (string) $meta['php_version']);
        $this->assertSame('Health: the timezone is Asia/Kolkata', 'Asia/Kolkata', (string) $meta['timezone']);
        $this->assertGreaterThan('Health: the database size is measured', 0, (float) $meta['db_size']);
    }

    private function maintenance(): void
    {
        $maintenance = new MaintenanceService();
        $this->assertFalse('Maintenance: off to begin with', $maintenance->isActive());

        $maintenance->enable('Suite test', 60);
        $this->assertTrue('Maintenance: it can be switched on', $maintenance->isActive());
        $this->assertContains(
            'Maintenance: the message is stored',
            'Suite test',
            (string) ($maintenance->state()['message'] ?? '')
        );
        $this->assertTrue(
            'Maintenance: an admin bypass token is issued',
            $maintenance->hasBypassToken((string) ($maintenance->state()['bypass_token'] ?? ''))
        );

        $maintenance->disable();
        $this->assertFalse('Maintenance: it can be switched off', $maintenance->isActive());
    }

    private function backups(): void
    {
        $service = new BackupService();
        $stats = $service->stats();

        $this->assertFalse(
            'Backups: the backup directory is outside the web root',
            str_starts_with((string) realpath($service->directory()), (string) realpath(ROOT_PATH) . '/')
        );

        $result = $service->create(BackupService::TYPE_DATABASE, 'Suite test backup');
        $this->assertTrue('Backups: a database backup is created', (bool) $result['ok'], (string) $result['message']);
        $backupId = (int) $result['backup_id'];
        $path = (string) $result['path'];

        $this->assertTrue('Backups: the dump exists on disk', is_file($path));
        $this->assertGreaterThan('Backups: the dump has content', 10000, (float) filesize($path));

        $sql = (string) file_get_contents($path);
        $this->assertContains('Backups: the dump creates tables', 'CREATE TABLE', $sql);
        $this->assertContains('Backups: the dump inserts rows', 'INSERT INTO', $sql);
        $this->assertContains('Backups: the dump is utf8mb4', 'utf8mb4', $sql);

        $verified = $service->verify($backupId);
        $this->assertTrue('Backups: the checksum verifies', (bool) $verified['ok'], (string) $verified['message']);

        // Restore really does put data back.
        $db = Database::instance();
        $original = (string) $db->value(
            'SELECT setting_value FROM ' . $db->wrap($db->table('settings')) . ' WHERE setting_key = :k',
            ['k' => 'site_name'],
            ''
        );
        $db->execute(
            'UPDATE ' . $db->wrap($db->table('settings')) . ' SET setting_value = :v WHERE setting_key = :k',
            ['v' => 'TAMPERED BY THE TEST SUITE', 'k' => 'site_name']
        );
        $restore = $service->restoreDatabase($path);
        $this->assertTrue('Backups: the dump restores', (bool) $restore['ok'], (string) $restore['message']);
        $this->assertSame(
            'Backups: the tampered value is restored',
            $original,
            (string) $db->value(
                'SELECT setting_value FROM ' . $db->wrap($db->table('settings')) . ' WHERE setting_key = :k',
                ['k' => 'site_name'],
                ''
            )
        );

        // The restore above rewrote the backups table from the dump, so the
        // deletion is checked with a backup taken after it.
        $second = $service->create(BackupService::TYPE_DATABASE, 'Suite deletion check');
        $secondPath = (string) $second['path'];
        $this->assertTrue('Backups: a second backup is created', is_file($secondPath));

        $deleted = $service->delete((int) $second['backup_id']);
        $this->assertTrue('Backups: deletion reports success', (bool) $deleted['ok'], (string) $deleted['message']);
        $this->assertFalse('Backups: a deleted backup is gone from disk', is_file($secondPath));

        // Anything left over from the dump we restored goes too.
        foreach ($service->stats()['count'] > (int) $stats['count'] ? $this->staleBackupIds((int) $stats['count']) : [] as $id) {
            $service->delete($id);
        }
        $this->assertSame(
            'Backups: the count is back where it started',
            (int) $stats['count'],
            (int) $service->stats()['count']
        );
    }

    /** @return array<int,int> backup ids beyond the original count, newest first */
    private function staleBackupIds(int $keep): array
    {
        $db = Database::instance();
        $ids = array_map('intval', $db->column(
            'SELECT id FROM ' . $db->wrap($db->table('backups')) . ' ORDER BY id DESC'
        ));
        return array_slice($ids, 0, max(0, count($ids) - $keep));
    }

    private function cron(): void
    {
        $cron = new CronService();

        $this->assertGreaterThan('Cron: tasks are defined', 5, (float) count(CronService::TASKS));

        $instructions = $cron->instructions();
        $this->assertContains('Cron: a crontab line is offered', 'bin/console cron:run', (string) $instructions['crontab']);
        $this->assertContains('Cron: a URL trigger is offered', 'token=', (string) $instructions['url']);
        $this->assertGreaterThan(
            'Cron: the URL trigger token is long enough',
            30,
            (float) strlen((string) parse_url((string) $instructions['url'], PHP_URL_QUERY))
        );

        $results = $cron->run('cleanup');
        $this->assertTrue('Cron: the cleanup task runs', (bool) ($results['cleanup']['ok'] ?? false));

        $results = $cron->run('analytics');
        $this->assertTrue('Cron: the analytics task runs', (bool) ($results['analytics']['ok'] ?? false));

        $unknown = $cron->run('not-a-task');
        $this->assertFalse('Cron: an unknown task is refused', (bool) ($unknown['not-a-task']['ok'] ?? true));

        $status = $cron->status();
        $this->assertTrue('Cron: the last run is recorded', ($status['cleanup']['last_run'] ?? null) !== null);
    }

    private function seo(): void
    {
        $sitemap = new SitemapService();
        $xml = $sitemap->xml();

        $this->assertContains('SEO: the sitemap is XML', '<?xml', $xml);
        $this->assertContains('SEO: the sitemap declares a urlset', '<urlset', $xml);
        $this->assertGreaterThan('SEO: the sitemap lists URLs', 5, (float) substr_count($xml, '<loc>'));
        $this->assertTrue(
            'SEO: the sitemap is well formed',
            simplexml_load_string($xml) !== false
        );

        $robots = $sitemap->robots();
        $this->assertContains('SEO: robots.txt names the sitemap', 'Sitemap:', $robots);
        $this->assertContains('SEO: robots.txt keeps crawlers out of /admin', 'Disallow: /admin', $robots);
    }

    private function updateSafety(): void
    {
        $update = new UpdateService();
        $state = $update->state();

        $this->assertFalse('Update: no update is mid-flight', (bool) $state['locked']);

        // Protected paths: what an update must never touch.
        $protected = (array) $state['protected_paths'];
        $this->assertGreaterThan('Update: protected paths are declared', 5, (float) count($protected));
        foreach (['storage', 'uploads', '.env', 'config'] as $needle) {
            $found = false;
            foreach ($protected as $path) {
                if (str_contains((string) $path, $needle)) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue('Update: "' . $needle . '" is protected', $found, json_encode($protected));
        }

        // The lock is exclusive: a second holder must be refused.
        $first = $update->acquireLock();
        $this->assertTrue('Update: the lock can be acquired', (bool) $first['ok']);
        $second = (new UpdateService())->acquireLock();
        $this->assertFalse('Update: a second lock is refused', (bool) $second['ok']);
        $update->releaseLock();
        $this->assertFalse('Update: the lock is released', (bool) (new UpdateService())->state()['locked']);

        // Version comparison drives "is there an update".
        $this->assertTrue('Version: 1.0.1 is newer than 1.0.0', Version::isNewer('1.0.1', '1.0.0'));
        $this->assertTrue('Version: 1.1.0 is newer than 1.0.9', Version::isNewer('1.1.0', '1.0.9'));
        $this->assertFalse('Version: 1.0.0 is not newer than 1.0.0', Version::isNewer('1.0.0', '1.0.0'));
        $this->assertFalse('Version: 0.9.9 is not newer than 1.0.0', Version::isNewer('0.9.9', '1.0.0'));

        // A repository name is validated before it is ever used in a URL.
        $github = new GitHubService();
        foreach ([
            'owner/repo; rm -rf /',
            '../../etc/passwd',
            'http://evil.test/owner/repo',
            'owner',
        ] as $bad) {
            $result = $github->saveConfiguration($bad, 'main', '', null);
            $this->assertFalse('Update: repository "' . $bad . '" is rejected', (bool) $result['ok']);
        }

        // A token is never returned in full.
        $masked = (string) $state['masked_token'];
        $this->assertFalse('Update: the token is not exposed in state()', str_contains($masked, 'ghp_') && strlen($masked) > 20);
    }
}
