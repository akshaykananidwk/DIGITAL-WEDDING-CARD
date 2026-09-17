<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Config;
use App\Core\Database;
use App\Core\Http\HttpClient;
use App\Core\Logger;
use App\Core\Migrator;
use App\Core\Str;
use App\Core\Url;
use App\Core\Version;
use App\Repositories\BackupRepository;
use App\Repositories\CronRepository;
use App\Repositories\HealthRepository;
use App\Repositories\UpdateRepository;

/**
 * System → Health.
 *
 * Also the post-update verification step: run() returns a structured result
 * whose `status` the update system uses to decide between success and
 * rollback, so the same checks a human reads are the ones that gate a deploy.
 */
final class HealthService
{
    public const OK       = 'healthy';
    public const WARNING  = 'warning';
    public const CRITICAL = 'critical';

    /** Extensions the application genuinely needs. */
    private const REQUIRED_EXTENSIONS = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'fileinfo'];
    /** Extensions that enable optional features. */
    private const RECOMMENDED_EXTENSIONS = ['gd', 'zip', 'curl', 'intl', 'exif', 'zlib'];

    /** Tables that must exist for the application to function. */
    private const CORE_TABLES = [
        'users', 'roles', 'permissions', 'categories', 'subcategories',
        'templates', 'template_fields', 'invitations', 'invitation_data',
        'settings', 'migrations',
    ];

    public function __construct(
        private readonly HealthRepository $repository = new HealthRepository()
    ) {
    }

    /**
     * Run every check.
     *
     * @param string $context manual|cron|update
     * @return array{status:string,checks:array<int,array<string,mixed>>,summary:array<string,int>,meta:array<string,mixed>}
     */
    public function run(string $context = 'manual', bool $record = true): array
    {
        $checks = [];

        $checks[] = $this->checkPhpVersion();
        $checks = array_merge($checks, $this->checkExtensions());
        $checks[] = $this->checkDatabaseConnection();
        $checks[] = $this->checkSchema();
        $checks[] = $this->checkMigrations();
        $checks = array_merge($checks, $this->checkWritablePaths());
        $checks[] = $this->checkDiskSpace();
        $checks[] = $this->checkConfiguration();
        $checks[] = $this->checkSecretLocation();
        $checks[] = $this->checkPdfEngine();
        $checks[] = $this->checkQrEngine();
        $checks[] = $this->checkFonts();
        $checks[] = $this->checkCache();
        $checks[] = $this->checkRoutes();
        $checks[] = $this->checkOutboundHttp();
        $checks[] = $this->checkAiConfiguration();
        $checks[] = $this->checkMailConfiguration();
        $checks[] = $this->checkBackups();
        $checks[] = $this->checkCron();
        $checks[] = $this->checkHttps();
        $checks[] = $this->checkInstallerRemoved();
        $checks[] = $this->checkUpdateState();

        $summary = [self::OK => 0, self::WARNING => 0, self::CRITICAL => 0];
        foreach ($checks as $check) {
            $summary[$check['status']] = ($summary[$check['status']] ?? 0) + 1;
        }

        $status = $summary[self::CRITICAL] > 0
            ? self::CRITICAL
            : ($summary[self::WARNING] > 0 ? self::WARNING : self::OK);

        $meta = $this->meta();

        if ($record) {
            try {
                $this->repository->record([
                    'status'      => $status,
                    'php_version' => PHP_VERSION,
                    'db_version'  => $meta['db_version'],
                    'app_version' => Version::current(),
                    'disk_free'   => $meta['disk_free'],
                    'disk_total'  => $meta['disk_total'],
                    'results'     => $checks,
                    'context'     => $context,
                    'note'        => $summary[self::CRITICAL] . ' critical, ' . $summary[self::WARNING] . ' warning',
                ]);
            } catch (\Throwable $e) {
                Logger::warning('Could not record the health check: ' . $e->getMessage());
            }
        }

        return ['status' => $status, 'checks' => $checks, 'summary' => $summary, 'meta' => $meta];
    }

    /** The subset of checks that must pass for an update to be accepted. */
    public function runCritical(): array
    {
        $checks = [
            $this->checkDatabaseConnection(),
            $this->checkSchema(),
            $this->checkMigrations(),
            $this->checkPhpVersion(),
            $this->checkConfiguration(),
            $this->checkRoutes(),
            $this->checkPdfEngine(),
        ];
        $checks = array_merge($checks, $this->checkExtensions(true));
        $checks = array_merge($checks, $this->checkWritablePaths());

        $failed = array_values(array_filter(
            $checks,
            static fn (array $check): bool => $check['status'] === self::CRITICAL
        ));

        return [
            'status' => $failed === [] ? self::OK : self::CRITICAL,
            'checks' => $checks,
            'failed' => $failed,
        ];
    }

    /** @return array<string,mixed> */
    public function meta(): array
    {
        $free = @disk_free_space(ROOT_PATH);
        $total = @disk_total_space(ROOT_PATH);

        $dbVersion = 'unavailable';
        $dbSize = 0;
        try {
            $db = Database::instance();
            $dbVersion = $db->version();
            $dbSize = $db->sizeBytes();
        } catch (\Throwable) {
            // Reported by the connection check.
        }

        return [
            'app_version'   => Version::current(),
            'installed_version' => Version::installed(),
            'php_version'   => PHP_VERSION,
            'php_sapi'      => PHP_SAPI,
            'db_version'    => $dbVersion,
            'db_size'       => $dbSize,
            'disk_free'     => $free === false ? 0 : (int) $free,
            'disk_total'    => $total === false ? 0 : (int) $total,
            'memory_limit'  => (string) ini_get('memory_limit'),
            'max_upload'    => (string) ini_get('upload_max_filesize'),
            'post_max'      => (string) ini_get('post_max_size'),
            'max_execution' => (string) ini_get('max_execution_time'),
            'timezone'      => date_default_timezone_get(),
            'server_time'   => date('Y-m-d H:i:s'),
            'os'            => PHP_OS_FAMILY,
            'server'        => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'cli'),
            'cache'         => Cache::stats(),
        ];
    }

    // ------------------------------------------------------------------
    //  Individual checks
    // ------------------------------------------------------------------

    private function check(string $key, string $label, string $status, string $message, array $extra = []): array
    {
        return array_merge([
            'key'     => $key,
            'label'   => $label,
            'status'  => $status,
            'message' => $message,
        ], $extra);
    }

    private function checkPhpVersion(): array
    {
        $minimum = Version::minPhp();
        if (version_compare(PHP_VERSION, $minimum, '<')) {
            return $this->check('php', 'PHP version', self::CRITICAL,
                'PHP ' . PHP_VERSION . ' is below the required ' . $minimum . '.');
        }
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            return $this->check('php', 'PHP version', self::WARNING,
                'PHP ' . PHP_VERSION . ' works, but 8.1 or newer is recommended.');
        }
        return $this->check('php', 'PHP version', self::OK, 'PHP ' . PHP_VERSION);
    }

    /** @return array<int,array<string,mixed>> */
    private function checkExtensions(bool $requiredOnly = false): array
    {
        $missingRequired = [];
        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (!extension_loaded($extension)) {
                $missingRequired[] = $extension;
            }
        }
        $checks = [
            $missingRequired === []
                ? $this->check('ext_required', 'Required PHP extensions', self::OK,
                    implode(', ', self::REQUIRED_EXTENSIONS) . ' all loaded.')
                : $this->check('ext_required', 'Required PHP extensions', self::CRITICAL,
                    'Missing: ' . implode(', ', $missingRequired)),
        ];

        if ($requiredOnly) {
            return $checks;
        }

        $missingRecommended = [];
        foreach (self::RECOMMENDED_EXTENSIONS as $extension) {
            if (!extension_loaded($extension)) {
                $missingRecommended[] = $extension;
            }
        }
        $checks[] = $missingRecommended === []
            ? $this->check('ext_recommended', 'Recommended PHP extensions', self::OK, 'All present.')
            : $this->check('ext_recommended', 'Recommended PHP extensions', self::WARNING,
                'Missing: ' . implode(', ', $missingRecommended)
                . ' (image processing, ZIP backups, updates or transliteration may be limited).');

        return $checks;
    }

    private function checkDatabaseConnection(): array
    {
        try {
            $db = Database::instance();
            $value = $db->value('SELECT 1');
            if ((int) $value !== 1) {
                return $this->check('db', 'Database connection', self::CRITICAL, 'The database did not answer correctly.');
            }
            return $this->check('db', 'Database connection', self::OK, $db->version());
        } catch (\Throwable $e) {
            return $this->check('db', 'Database connection', self::CRITICAL, 'Cannot connect to the database.');
        }
    }

    private function checkSchema(): array
    {
        try {
            $db = Database::instance();
            $missing = [];
            foreach (self::CORE_TABLES as $table) {
                if (!$db->tableExists($table)) {
                    $missing[] = $table;
                }
            }
            if ($missing !== []) {
                return $this->check('schema', 'Database schema', self::CRITICAL,
                    'Missing table(s): ' . implode(', ', $missing));
            }
            return $this->check('schema', 'Database schema', self::OK,
                count($db->tables()) . ' tables present.');
        } catch (\Throwable $e) {
            return $this->check('schema', 'Database schema', self::CRITICAL, 'Could not inspect the schema.');
        }
    }

    private function checkMigrations(): array
    {
        try {
            $migrator = new Migrator(Database::instance());
            $pending = $migrator->pending();
            if ($pending !== []) {
                return $this->check('migrations', 'Migrations', self::CRITICAL,
                    count($pending) . ' migration(s) pending: ' . implode(', ', array_slice($pending, 0, 3)),
                    ['pending' => $pending]);
            }
            return $this->check('migrations', 'Migrations', self::OK,
                count($migrator->applied()) . ' applied, none pending.');
        } catch (\Throwable $e) {
            return $this->check('migrations', 'Migrations', self::CRITICAL, 'Could not read the migration state.');
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function checkWritablePaths(): array
    {
        $paths = [
            'storage/logs'     => STORAGE_PATH . '/logs',
            'storage/cache'    => STORAGE_PATH . '/cache',
            'storage/sessions' => STORAGE_PATH . '/sessions',
            'storage/backups'  => STORAGE_PATH . '/backups',
            'storage/tmp'      => STORAGE_PATH . '/tmp',
            'uploads'          => UPLOAD_PATH,
        ];

        $failed = [];
        foreach ($paths as $label => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            if (!is_dir($path) || !is_writable($path)) {
                $failed[] = $label;
            }
        }

        return [
            $failed === []
                ? $this->check('writable', 'Directory permissions', self::OK, 'All runtime directories are writable.')
                : $this->check('writable', 'Directory permissions', self::CRITICAL,
                    'Not writable: ' . implode(', ', $failed) . '. Set them to 755 and check ownership.'),
        ];
    }

    private function checkDiskSpace(): array
    {
        $free = @disk_free_space(ROOT_PATH);
        $total = @disk_total_space(ROOT_PATH);
        if ($free === false || $total === false || $total <= 0) {
            return $this->check('disk', 'Disk space', self::WARNING, 'Disk usage could not be measured.');
        }
        $usedPercent = (int) round((($total - $free) / $total) * 100);
        $message = Str::bytesToHuman((float) $free) . ' free of ' . Str::bytesToHuman((float) $total)
            . ' (' . $usedPercent . '% used)';

        if ($free < 52428800) { // < 50 MB
            return $this->check('disk', 'Disk space', self::CRITICAL, $message . ' - too low for a backup or update.');
        }
        if ($usedPercent >= 90) {
            return $this->check('disk', 'Disk space', self::WARNING, $message);
        }
        return $this->check('disk', 'Disk space', self::OK, $message);
    }

    private function checkConfiguration(): array
    {
        if (!Config::isInstalled()) {
            return $this->check('config', 'Configuration', self::CRITICAL, 'The application is not installed.');
        }
        $issues = [];
        if ((string) Config::get('app.key', '') === '') {
            $issues[] = 'the application key is missing';
        }
        if ((bool) Config::get('app.debug', false)) {
            $issues[] = 'debug mode is ON (turn it off in production)';
        }
        if ((string) Config::get('app.url', '') === '') {
            $issues[] = 'the site URL is not set';
        }

        if ($issues === []) {
            return $this->check('config', 'Configuration', self::OK, 'Application key set, debug mode off.');
        }
        $critical = in_array('the application key is missing', $issues, true);
        return $this->check('config', 'Configuration', $critical ? self::CRITICAL : self::WARNING,
            ucfirst(implode('; ', $issues)) . '.');
    }

    private function checkSecretLocation(): array
    {
        $file = Config::secretFile();
        if ($file === null) {
            return $this->check('secrets', 'Secret storage', self::CRITICAL, 'No configuration file was found.');
        }
        $outsideWebRoot = !str_starts_with($file, ROOT_PATH . DIRECTORY_SEPARATOR);
        if ($outsideWebRoot) {
            return $this->check('secrets', 'Secret storage', self::OK,
                'Credentials are stored outside the web root.');
        }
        /*
         * Inside the web root, a deny rule is the only thing standing between
         * the credentials and the internet - and Nginx and LiteSpeed ignore
         * .htaccess, so on those the rule has to be in the server config. That
         * is worth proving rather than assuming, so the file is requested over
         * HTTP the way a stranger would.
         */
        $relative = ltrim(str_replace(ROOT_PATH, '', $file), '/\\');
        $exposure = $this->fetchOwnUrl(Url::to($relative));

        if ($exposure['served'] === true) {
            return $this->check('secrets', 'Secret storage', self::CRITICAL,
                'Credentials in storage/config are being served over HTTP at /' . $relative
                . ' - deny /storage/ in the server configuration now, then rotate the database '
                . 'password and the application key.');
        }
        if ($exposure['served'] === null) {
            return $this->check('secrets', 'Secret storage', self::WARNING,
                'Credentials are stored in storage/config. This host could not check whether '
                . '/' . $relative . ' is reachable (' . $exposure['message'] . '); confirm it '
                . 'returns 403 or 404, and deny /storage/ in the server configuration if it does not.');
        }
        return $this->check('secrets', 'Secret storage', self::WARNING,
            'Credentials are stored in storage/config, confirmed unreachable over HTTP. '
            . 'Moving them outside the web root is stronger.');
    }

    /**
     * Request one of our own URLs and report whether its body came back.
     *
     * Goes through the shared HttpClient so there is one outbound path and its
     * SSRF guard stays intact - which also means a site served from localhost
     * or a private address cannot be checked this way, and that is reported as
     * "could not determine" rather than as a pass.
     *
     * @return array{served:bool|null,message:string} served: true exposed,
     *         false denied, null could not be determined
     */
    private function fetchOwnUrl(string $url): array
    {
        if (!HttpClient::isAvailable()) {
            return ['served' => null, 'message' => 'this host has no outbound HTTP'];
        }

        try {
            $response = (new HttpClient(5, 5))->get($url);
        } catch (\Throwable $e) {
            return ['served' => null, 'message' => $e->getMessage()];
        }

        $status = (int) ($response['status'] ?? 0);
        if ($status === 0) {
            return ['served' => null, 'message' => (string) ($response['error'] ?? 'no response')];
        }

        return [
            'served'  => self::exposesSource($status, (string) ($response['body'] ?? '')),
            'message' => 'HTTP ' . $status,
        ];
    }

    /**
     * Did that response hand out PHP source?
     *
     * A 200 only means exposure if the file's own text comes back. A server
     * that executes the file returns an empty body, which discloses nothing,
     * and anything other than 200 is a refusal. Public so the decision can be
     * tested without a live host.
     */
    public static function exposesSource(int $status, string $body): bool
    {
        return $status === 200 && str_contains($body, '<?php');
    }

    private function checkPdfEngine(): array
    {
        $result = (new PdfService())->selfTest();
        if (!($result['ok'] ?? false)) {
            return $this->check('pdf', 'PDF engine', self::CRITICAL, (string) ($result['message'] ?? 'PDF generation failed.'));
        }
        return $this->check('pdf', 'PDF engine', self::OK,
            (string) $result['message'] . ' Engine: ' . (string) ($result['engine'] ?? 'builtin') . '.');
    }

    private function checkQrEngine(): array
    {
        try {
            $qr = \App\Core\Qr\QrCode::encode('https://example.com/health-check', 'M');
            if ($qr->size() < 21) {
                return $this->check('qr', 'QR engine', self::CRITICAL, 'The QR encoder produced an invalid matrix.');
            }
            if (!function_exists('imagepng')) {
                return $this->check('qr', 'QR engine', self::WARNING,
                    'QR codes work as SVG, but PNG output needs the GD extension.');
            }
            $png = $qr->toPng(4, 2);
            return strlen($png) > 100
                ? $this->check('qr', 'QR engine', self::OK, 'PNG and SVG output working.')
                : $this->check('qr', 'QR engine', self::CRITICAL, 'PNG rendering failed.');
        } catch (\Throwable $e) {
            return $this->check('qr', 'QR engine', self::CRITICAL, 'QR error: ' . $e->getMessage());
        }
    }

    private function checkFonts(): array
    {
        $fonts = (new PdfService())->fontFiles();
        $hasGujarati = isset($fonts['gujarati']);
        $hasDevanagari = isset($fonts['devanagari']);

        if ($fonts === []) {
            return $this->check('fonts', 'Fonts', self::CRITICAL, 'No embeddable font is available for PDF export.');
        }
        if (!$hasGujarati || !$hasDevanagari) {
            $missing = [];
            if (!$hasGujarati) {
                $missing[] = 'Gujarati';
            }
            if (!$hasDevanagari) {
                $missing[] = 'Hindi';
            }
            return $this->check('fonts', 'Fonts', self::WARNING,
                'Missing a PDF font for: ' . implode(', ', $missing) . '. Upload one in Admin → Fonts.');
        }
        return $this->check('fonts', 'Fonts', self::OK,
            count($fonts) . ' PDF fonts available, including Gujarati and Hindi.');
    }

    private function checkCache(): array
    {
        try {
            $key = 'health:' . bin2hex(random_bytes(4));
            Cache::put($key, 'ok', 30);
            $value = Cache::get($key);
            Cache::forget($key);
            if ($value !== 'ok') {
                return $this->check('cache', 'Cache', self::WARNING, 'The cache did not return what was written.');
            }
            $stats = Cache::stats();
            return $this->check('cache', 'Cache', self::OK,
                $stats['entries'] . ' entries, ' . Str::bytesToHuman((float) $stats['bytes']) . '.');
        } catch (\Throwable $e) {
            return $this->check('cache', 'Cache', self::WARNING, 'Cache error: ' . $e->getMessage());
        }
    }

    /** Are the critical routes registered? Catches a broken deploy. */
    private function checkRoutes(): array
    {
        try {
            $router = \App\Core\Application::getInstance()->router();
            if ($router->routeCount() === 0) {
                \App\Core\Application::getInstance()->boot();
            }
            $required = [
                ['GET', '/'],
                ['GET', '/login'],
                ['GET', '/dashboard'],
                ['GET', '/admin'],
                ['GET', '/invite/health-check-slug'],
                ['GET', '/templates'],
            ];
            $missing = [];
            foreach ($required as [$method, $path]) {
                if ($router->match($method, $path) === null) {
                    $missing[] = $method . ' ' . $path;
                }
            }
            if ($missing !== []) {
                return $this->check('routes', 'Routes', self::CRITICAL, 'Unreachable: ' . implode(', ', $missing));
            }
            return $this->check('routes', 'Routes', self::OK, $router->routeCount() . ' routes registered.');
        } catch (\Throwable $e) {
            return $this->check('routes', 'Routes', self::CRITICAL, 'Routing failed to boot: ' . $e->getMessage());
        }
    }

    private function checkOutboundHttp(): array
    {
        if (!HttpClient::isAvailable()) {
            return $this->check('http', 'Outbound HTTPS', self::WARNING,
                'Neither cURL nor allow_url_fopen is available; updates and AI cannot reach the internet.');
        }
        return $this->check('http', 'Outbound HTTPS', self::OK,
            function_exists('curl_init') ? 'cURL available.' : 'Stream wrappers available.');
    }

    private function checkAiConfiguration(): array
    {
        $ai = new AiService();
        if (!$ai->isConfigured()) {
            return $this->check('ai', 'AI (Gemini)', self::OK,
                'Not configured. AI features are optional and currently switched off.');
        }
        return $this->check('ai', 'AI (Gemini)', self::OK, 'Configured and enabled.');
    }

    private function checkMailConfiguration(): array
    {
        $driver = (string) Config::get('mail.driver', 'mail');
        if ($driver === 'smtp' && (string) Config::get('mail.smtp.host', '') === '') {
            return $this->check('mail', 'Email', self::WARNING, 'SMTP is selected but no host is configured.');
        }
        if ((string) Config::get('mail.from_address', '') === '') {
            return $this->check('mail', 'Email', self::WARNING,
                'No sender address is configured; password reset emails may be rejected.');
        }
        return $this->check('mail', 'Email', self::OK, 'Driver: ' . $driver . '.');
    }

    private function checkBackups(): array
    {
        try {
            $latest = (new BackupRepository())->latestCompleted();
            if ($latest === null) {
                return $this->check('backup', 'Backups', self::WARNING, 'No backup has been created yet.');
            }
            $age = time() - (int) strtotime((string) ($latest['completed_at'] ?? $latest['created_at']));
            $message = 'Last backup ' . $this->humanAge($age) . ' (' . Str::bytesToHuman((float) $latest['size']) . ').';
            return $age > 2592000 // 30 days
                ? $this->check('backup', 'Backups', self::WARNING, $message)
                : $this->check('backup', 'Backups', self::OK, $message);
        } catch (\Throwable $e) {
            return $this->check('backup', 'Backups', self::WARNING, 'Could not read the backup history.');
        }
    }

    private function checkCron(): array
    {
        try {
            $last = (new CronRepository())->lastRun();
            if ($last === null) {
                return $this->check('cron', 'Scheduled tasks', self::WARNING,
                    'Cron has never run. See the command in System → Cron.');
            }
            $age = time() - (int) strtotime((string) $last['ran_at']);
            $message = 'Last run ' . $this->humanAge($age) . ' (' . $last['task'] . ').';
            return $age > 172800 // 48 hours
                ? $this->check('cron', 'Scheduled tasks', self::WARNING, $message)
                : $this->check('cron', 'Scheduled tasks', self::OK, $message);
        } catch (\Throwable $e) {
            return $this->check('cron', 'Scheduled tasks', self::WARNING, 'Could not read the cron history.');
        }
    }

    private function checkHttps(): array
    {
        if (PHP_SAPI === 'cli') {
            return $this->check('https', 'HTTPS', self::OK, 'Not applicable on the command line.');
        }
        if (Url::isSecure()) {
            return $this->check('https', 'HTTPS', self::OK, 'The site is served over HTTPS.');
        }
        return $this->check('https', 'HTTPS', self::WARNING,
            'This request was not HTTPS. Install an SSL certificate and enable "Force HTTPS" in Security settings.');
    }

    private function checkInstallerRemoved(): array
    {
        if (!Config::isInstalled()) {
            return $this->check('installer', 'Installer', self::WARNING, 'Installation has not completed.');
        }
        // The installer refuses to run once installed, so this is informational.
        return $this->check('installer', 'Installer', self::OK,
            'Installation is locked; /install now shows "already installed".');
    }

    private function checkUpdateState(): array
    {
        try {
            $updates = new UpdateRepository();
            $stuck = $updates->inProgress();
            if ($stuck !== null) {
                $age = time() - (int) strtotime((string) $stuck['started_at']);
                if ($age > 1800) {
                    return $this->check('update', 'Update state', self::CRITICAL,
                        'An update stalled at "' . $stuck['step'] . '", started ' . $this->humanAge($age)
                        . '. Clear the lock in System → Updates.');
                }
                return $this->check('update', 'Update state', self::WARNING,
                    'An update is currently running (' . $stuck['step'] . ').');
            }
            $last = $updates->latest();
            if ($last === null) {
                return $this->check('update', 'Update state', self::OK, 'No updates have been applied yet.');
            }
            if (in_array((string) $last['status'], ['failed', 'rolled_back'], true)) {
                return $this->check('update', 'Update state', self::WARNING,
                    'The last update ' . str_replace('_', ' ', (string) $last['status'])
                    . '. Review the history in System → Updates.');
            }
            return $this->check('update', 'Update state', self::OK,
                'Last update succeeded (version ' . (string) $last['to_version'] . ').');
        } catch (\Throwable $e) {
            return $this->check('update', 'Update state', self::WARNING, 'Could not read the update history.');
        }
    }

    private function humanAge(int $seconds): string
    {
        if ($seconds < 60) {
            return 'just now';
        }
        if ($seconds < 3600) {
            return (int) floor($seconds / 60) . ' minute(s) ago';
        }
        if ($seconds < 86400) {
            return (int) floor($seconds / 3600) . ' hour(s) ago';
        }
        return (int) floor($seconds / 86400) . ' day(s) ago';
    }

    /** Emoji indicator used in the admin UI. */
    public static function indicator(string $status): string
    {
        return match ($status) {
            self::OK       => '🟢',
            self::WARNING  => '🟡',
            default        => '🔴',
        };
    }

    public function latest(): ?array
    {
        return $this->repository->latest();
    }

    public function history(int $limit = 20): array
    {
        return $this->repository->recent($limit);
    }
}
