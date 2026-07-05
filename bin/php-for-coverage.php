#!/usr/bin/env php
<?php

/**
 * Run PHP with a code-coverage driver configured for PHPUnit/Pest.
 *
 * Prefers PCOV (lighter, faster) when the extension is available for the
 * current PHP binary, otherwise falls back to Xdebug coverage mode.
 *
 * Usage:
 *   php bin/php-for-coverage.php artisan test --coverage-php build/coverage/pgsql.php
 */

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/php-for-coverage.php <command...>\n");
    exit(1);
}

$ini = ['-d', 'memory_limit=-1'];
$env = array_merge($_ENV, [
    'COVERAGE_COLLECTING' => '1',
    // The -d flags above only reach the process we spawn directly; artisan
    // re-execs paratest (and paratest its workers) as fresh php processes that
    // fall back to php.ini's memory_limit. A scan-dir ini appended after the
    // defaults (leading path separator = keep defaults first) lifts the limit
    // for every php process in the coverage tree — the aggregation step is
    // what outgrows the stock limit as the suite grows.
    'PHP_INI_SCAN_DIR' => PATH_SEPARATOR.coverageIniScanDir(),
]);

$pcovExtension = resolvePcovExtensionPath();

if ($pcovExtension !== null) {
    $ini[] = '-d';
    $ini[] = 'extension='.$pcovExtension;
    $ini[] = '-d';
    $ini[] = 'pcov.enabled=1';
    $ini[] = '-d';
    $ini[] = 'pcov.directory='.dirname(__DIR__);
    $ini[] = '-d';
    $ini[] = 'xdebug.mode=off';
    $env['XDEBUG_MODE'] = 'off';
} elseif (extension_loaded('pcov')) {
    $ini[] = '-d';
    $ini[] = 'pcov.enabled=1';
    $ini[] = '-d';
    $ini[] = 'pcov.directory='.dirname(__DIR__);
    $ini[] = '-d';
    $ini[] = 'xdebug.mode=off';
    $env['XDEBUG_MODE'] = 'off';
} elseif (extension_loaded('xdebug')) {
    $ini[] = '-d';
    $ini[] = 'xdebug.mode=coverage';
    $env['XDEBUG_MODE'] = 'coverage';
}

// When the wrapped command is paratest itself (invoked directly so the
// coverage-merging main process inherits our unlimited memory_limit — Herd's
// php ignores PHP_INI_SCAN_DIR and `artisan test --parallel` would re-exec
// paratest at the stock limit), replicate the env artisan test would set.
if (str_contains(implode(' ', $argv), 'paratest')) {
    $env['LARAVEL_PARALLEL_TESTING'] = '1';
}

$command = array_merge([PHP_BINARY], $ini, array_slice($argv, 1));

$process = proc_open(
    $command,
    [
        0 => STDIN,
        1 => STDOUT,
        2 => STDERR,
    ],
    $pipes,
    getcwd() ?: dirname(__DIR__),
    $env,
);

if (! is_resource($process)) {
    fwrite(STDERR, "Failed to start coverage process.\n");
    exit(1);
}

exit(proc_close($process));

function coverageIniScanDir(): string
{
    $dir = sys_get_temp_dir().'/signals-coverage-ini';

    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($dir.'/zz-signals-coverage.ini', "memory_limit=-1\n");

    return $dir;
}

function resolvePcovExtensionPath(): ?string
{
    if (extension_loaded('pcov')) {
        return null;
    }

    $extensionDir = ini_get('extension_dir');

    if (is_string($extensionDir) && $extensionDir !== '') {
        $candidate = rtrim($extensionDir, '/').'/pcov.so';

        if (is_file($candidate)) {
            return $candidate;
        }
    }

    $build = shell_exec(escapeshellarg(PHP_BINARY).' -i 2>/dev/null | grep "PHP Extension Build"');

    if (! is_string($build) || ! preg_match('/API(\d+)/', $build, $matches)) {
        return null;
    }

    $candidates = [
        '/opt/homebrew/lib/php/pecl/'.$matches[1].'/pcov.so',
        '/usr/local/lib/php/pecl/'.$matches[1].'/pcov.so',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}
