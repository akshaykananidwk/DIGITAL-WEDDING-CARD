#!/usr/bin/env php
<?php
/**
 * Test runner.
 *
 * Usage:
 *   php tests/run.php                 run every case
 *   php tests/run.php Security        run the cases whose name matches
 *   php tests/run.php --json          machine readable summary
 *
 * The suite runs against the installed application and its real database.
 * Cases that write data clean up after themselves; nothing here depends on
 * an internet connection except the update cases, which skip without one.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Tests run from the command line only.\n");
}

define('APP_START', microtime(true));
define('APP_CLI', true);
define('APP_TESTING', true);
define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/app/bootstrap.php';
require __DIR__ . '/TestCase.php';

$filter = null;
$asJson = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--json') {
        $asJson = true;
    } elseif (!str_starts_with($argument, '--')) {
        $filter = $argument;
    }
}

$cases = [];
foreach (glob(__DIR__ . '/Cases/*Test.php') ?: [] as $file) {
    require $file;
    $class = 'Tests\\Cases\\' . basename($file, '.php');
    if (!class_exists($class)) {
        continue;
    }
    /** @var Tests\TestCase $case */
    $case = new $class();
    if ($filter !== null && stripos($case->name(), $filter) === false && stripos($class, $filter) === false) {
        continue;
    }
    $cases[] = $case;
}

$colour = function_exists('posix_isatty') ? @posix_isatty(STDOUT) : false;
$paint = static function (string $text, string $code) use ($colour): string {
    return $colour ? "\033[" . $code . 'm' . $text . "\033[0m" : $text;
};

$total = 0;
$failed = 0;
$report = [];
$startedAt = microtime(true);

foreach ($cases as $case) {
    $caseStarted = microtime(true);
    try {
        $case->run();
    } catch (\Throwable $e) {
        $report[] = [
            'case'   => $case->name(),
            'assertions' => [['name' => 'case executed', 'ok' => false, 'message' => $e::class . ': ' . $e->getMessage()]],
            'ms'     => 0,
        ];
        $total++;
        $failed++;
        if (!$asJson) {
            echo $paint('■ ' . $case->name(), '1;37'), PHP_EOL;
            echo '  ', $paint('✗ case crashed', '0;31'), ' ', $e::class, ': ', $e->getMessage(), PHP_EOL;
        }
        continue;
    }

    $results = $case->results();
    $caseFailed = count(array_filter($results, static fn (array $r): bool => !$r['ok']));
    $total += count($results);
    $failed += $caseFailed;

    $report[] = [
        'case'       => $case->name(),
        'assertions' => $results,
        'ms'         => (int) round((microtime(true) - $caseStarted) * 1000),
    ];

    if ($asJson) {
        continue;
    }

    echo PHP_EOL, $paint('■ ' . $case->name(), '1;37'),
        ' ', $paint('(' . count($results) . ' checks, ' . (int) round((microtime(true) - $caseStarted) * 1000) . 'ms)', '0;90'),
        PHP_EOL;
    foreach ($results as $result) {
        if ($result['ok']) {
            echo '  ', $paint('✓', '0;32'), ' ', $result['name'];
            echo $result['message'] === '' ? '' : $paint('  ' . $result['message'], '0;90');
            echo PHP_EOL;
        } else {
            echo '  ', $paint('✗', '0;31'), ' ', $result['name'], PHP_EOL;
            echo '    ', $paint($result['message'], '0;31'), PHP_EOL;
        }
    }
}

$elapsed = (int) round((microtime(true) - $startedAt) * 1000);

if ($asJson) {
    echo json_encode([
        'total'  => $total,
        'failed' => $failed,
        'ms'     => $elapsed,
        'cases'  => $report,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
} else {
    echo PHP_EOL, str_repeat('─', 60), PHP_EOL;
    echo $failed === 0
        ? $paint('All ' . $total . ' checks passed', '0;32')
        : $paint($failed . ' of ' . $total . ' checks failed', '0;31');
    echo ' in ', $elapsed, 'ms across ', count($cases), ' case(s).', PHP_EOL;
}

exit($failed === 0 ? 0 : 1);
