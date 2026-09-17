<?php
/**
 * Boot the application as a host with `open_basedir` narrowed to the document
 * root would, and report what it decided.
 *
 * Run as a child process by SystemTest, because open_basedir can only ever be
 * narrowed - a process that restricts itself cannot undo it, and the rest of
 * the suite needs to reach the database socket.
 *
 * Usage: php -d open_basedir=<root>:<tmp> tests/support/restricted-host.php
 */

declare(strict_types=1);

define('APP_START', microtime(true));
define('APP_CLI', true);
define('APP_TESTING', true);
define('ROOT_PATH', dirname(__DIR__, 2));

$report = ['booted' => false, 'error' => null];

try {
    require ROOT_PATH . '/app/bootstrap.php';

    // This is the call that used to throw: it probes for the secret file,
    // which lives above the web root.
    App\Core\Config::load();
    $report['booted'] = true;

    $outside = dirname(ROOT_PATH) . '/' . App\Core\Config::EXTERNAL_DIR . '/app.php';

    $labels = [];
    foreach ((new App\Services\InstallService())->requirements()['groups'] as $items) {
        foreach ($items as $item) {
            $labels[] = (string) $item['label'];
        }
    }

    $report += [
        'restricted'        => App\Core\Path::isRestricted(),
        'restrictions'      => App\Core\Path::restrictions(),
        'allows_outside'    => App\Core\Path::allowed($outside),
        'is_file_outside'   => App\Core\Path::isFile($outside),
        'is_dir_outside'    => App\Core\Path::isDir(dirname($outside)),
        'writable_outside'  => App\Core\Path::isWritable(dirname($outside)),
        'makedir_outside'   => App\Core\Path::makeDir($outside . '/nope'),
        'allows_inside'     => App\Core\Path::allowed(STORAGE_PATH . '/cache'),
        'candidates'        => App\Core\Config::secretCandidates(),
        'install_target'    => App\Core\Config::preferredSecretTarget(),
        'requirement_shown' => in_array('open_basedir', $labels, true),
        'backup_directory'  => (new App\Services\BackupService())->directory(),
        'temp_dir'          => App\Core\Path::tempDir(),
    ];

    // The temporary file the PDF writer needs for an embedded QR: on a
    // restricted host sys_get_temp_dir() is out of bounds, which used to fail
    // the whole export.
    $temp = App\Core\Path::tempFile('probe');
    $report['temp_file'] = $temp;
    $report['temp_file_inside_root'] = is_string($temp) && str_starts_with($temp, ROOT_PATH . '/');
    if (is_string($temp)) {
        @unlink($temp);
    }

    // And the export itself, with no database in play: a page plus a QR image
    // placed from a string, which is exactly the call that raised.
    $pdf = new App\Core\Pdf\PdfDocument('A4');
    $pdf->addPage();
    $png = App\Core\Qr\QrCode::encode('https://example.test/i/ABC123')->toPng(4);
    $report['pdf_image_placed'] = $pdf->imageFromString($png, 20.0, 20.0, 40.0, 40.0);
    $bytes = $pdf->output();
    $report['pdf_bytes'] = strlen($bytes);
    $report['pdf_is_pdf'] = str_starts_with($bytes, '%PDF-');
    // Logging must survive a host where the application's own log file cannot
    // be written: the reason then goes to the host's PHP error log, which is
    // the only place an operator can still read it. A directory standing where
    // the log file belongs reproduces that without needing permissions this
    // process may be able to override.
    $channel = 'probeblocked';
    $blocker = STORAGE_PATH . '/logs/' . $channel . '-' . date('Y-m-d') . '.log';
    $report['log_fallback_ok'] = false;
    if (App\Core\Path::makeDir($blocker)) {
        $errorLog = (string) App\Core\Path::tempFile('hostlog');
        $previous = (string) ini_get('error_log');
        try {
            ini_set('error_log', $errorLog);
            App\Core\Logger::critical('probe: the host log is the last resort', [], $channel);
            $report['log_fallback_ok'] = str_contains((string) @file_get_contents($errorLog), 'last resort');
        } finally {
            ini_set('error_log', $previous);
            @unlink($errorLog);
            @rmdir($blocker);
        }
    }

} catch (\Throwable $e) {
    $report['error'] = $e::class . ': ' . $e->getMessage();
}

echo json_encode($report), "\n";
