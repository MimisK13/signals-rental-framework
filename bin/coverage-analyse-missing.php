#!/usr/bin/env php
<?php

/**
 * Analyse merged php-code-coverage exports and list classes missing full method coverage.
 *
 * Usage:
 *   php -d memory_limit=-1 bin/coverage-analyse-missing.php build/coverage
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\ProcessedClassType;
use SebastianBergmann\CodeCoverage\Data\ProcessedMethodType;
use SebastianBergmann\CodeCoverage\Data\ProcessedTraitType;
use SebastianBergmann\CodeCoverage\Node\File;

$dir = $argv[1] ?? null;

if (! is_string($dir) || $dir === '' || ! is_dir($dir)) {
    fwrite(STDERR, "Usage: php -d memory_limit=-1 bin/coverage-analyse-missing.php <coverage-directory>\n");
    exit(1);
}

$repoRoot = realpath(__DIR__.'/..');

if ($repoRoot === false) {
    fwrite(STDERR, "Could not resolve repository root.\n");
    exit(1);
}

$files = glob(rtrim($dir, '/').'/*.php');

if ($files === false || $files === []) {
    fwrite(STDERR, "No .php coverage files found in {$dir}\n");
    exit(1);
}

sort($files);

$merged = null;

foreach ($files as $file) {
    fwrite(STDERR, 'Loading '.basename($file)."...\n");

    try {
        $coverage = include $file;
    } catch (Throwable $e) {
        fwrite(STDERR, '  Error loading '.basename($file).': '.$e->getMessage()."\n");

        continue;
    }

    if (! $coverage instanceof CodeCoverage) {
        fwrite(STDERR, '  Skipping '.basename($file)." — not a CodeCoverage object\n");

        continue;
    }

    if ($merged === null) {
        $merged = $coverage;
    } else {
        $merged->merge($coverage);
    }
}

if ($merged === null) {
    fwrite(STDERR, "No valid coverage data found\n");
    exit(1);
}

$report = $merged->getReport();

/** @var list<array{
 *     fqcn: string,
 *     file: string,
 *     coveredMethods: int,
 *     totalMethods: int,
 *     uncoveredMethods: list<array{
 *         name: string,
 *         coverage: float,
 *         executedLines: int,
 *         executableLines: int,
 *         startLine: int,
 *     }>,
 * }> $missingClasses */
$missingClasses = [];

$totalClasses = 0;
$fullyCoveredClasses = 0;
$oneMethodAway = 0;
$totalMethods = 0;
$fullyCoveredMethods = 0;

foreach ($report as $item) {
    if (! $item instanceof File) {
        continue;
    }

    $relativeFile = repoRelativePath($item->pathAsString(), $repoRoot);

    foreach ($item->classesAndTraits() as $fqcn => $classLike) {
        if (! $classLike instanceof ProcessedClassType && ! $classLike instanceof ProcessedTraitType) {
            continue;
        }

        $methodStats = analyseClassMethods($classLike->methods);
        $totalClasses++;
        $totalMethods += $methodStats['totalMethods'];
        $fullyCoveredMethods += $methodStats['coveredMethods'];

        if ($methodStats['uncoveredMethods'] === []) {
            $fullyCoveredClasses++;

            continue;
        }

        if (count($methodStats['uncoveredMethods']) === 1) {
            $oneMethodAway++;
        }

        $missingClasses[] = [
            'fqcn' => $fqcn,
            'file' => $relativeFile,
            'coveredMethods' => $methodStats['coveredMethods'],
            'totalMethods' => $methodStats['totalMethods'],
            'uncoveredMethods' => $methodStats['uncoveredMethods'],
        ];
    }
}

usort(
    $missingClasses,
    static function (array $a, array $b): int {
        $uncoveredA = count($a['uncoveredMethods']);
        $uncoveredB = count($b['uncoveredMethods']);

        if ($uncoveredA !== $uncoveredB) {
            return $uncoveredA <=> $uncoveredB;
        }

        $linesA = uncoveredLineCount($a['uncoveredMethods']);
        $linesB = uncoveredLineCount($b['uncoveredMethods']);

        if ($linesA !== $linesB) {
            return $linesA <=> $linesB;
        }

        return $a['fqcn'] <=> $b['fqcn'];
    },
);

$classTarget = (int) ceil($totalClasses * 0.9);
$methodTarget = (int) ceil($totalMethods * 0.9);
$classesNeeded = max(0, $classTarget - $fullyCoveredClasses);
$methodsNeeded = max(0, $methodTarget - $fullyCoveredMethods);

$classPct = $totalClasses > 0 ? ($fullyCoveredClasses / $totalClasses) * 100 : 0.0;
$methodPct = $totalMethods > 0 ? ($fullyCoveredMethods / $totalMethods) * 100 : 0.0;

$output = [];
$output[] = sprintf(
    'Classes fully covered: %d/%d (%.2f%%) — need %d more for 90%%',
    $fullyCoveredClasses,
    $totalClasses,
    $classPct,
    $classesNeeded,
);
$output[] = sprintf(
    'Methods fully covered: %d/%d (%.2f%%) — need %d more for 90%%',
    $fullyCoveredMethods,
    $totalMethods,
    $methodPct,
    $methodsNeeded,
);
$output[] = '';
$output[] = sprintf('Classes missing exactly ONE method (quick wins for class %%): %d', $oneMethodAway);
$output[] = '';
$output[] = str_repeat('=', 100);

foreach ($missingClasses as $class) {
    $output[] = sprintf(
        '%s  [%d/%d methods covered]  %s',
        $class['fqcn'],
        $class['coveredMethods'],
        $class['totalMethods'],
        $class['file'],
    );

    foreach ($class['uncoveredMethods'] as $method) {
        $output[] = sprintf(
            '    %s() %s%%  (%d/%d lines)  :%d',
            $method['name'],
            formatCoverage($method['coverage']),
            $method['executedLines'],
            $method['executableLines'],
            $method['startLine'],
        );
    }
}

$text = implode("\n", $output)."\n";
$outFile = rtrim($dir, '/').'/missing-methods.txt';

file_put_contents($outFile, $text);

echo $text;

fwrite(STDERR, "Wrote {$outFile}\n");

/**
 * @param  array<string, ProcessedMethodType>  $methods
 * @return array{
 *     coveredMethods: int,
 *     totalMethods: int,
 *     uncoveredMethods: list<array{
 *         name: string,
 *         coverage: float,
 *         executedLines: int,
 *         executableLines: int,
 *         startLine: int,
 *     }>,
 * }
 */
function analyseClassMethods(array $methods): array
{
    $coveredMethods = 0;
    $totalMethods = 0;
    $uncoveredMethods = [];

    foreach ($methods as $method) {
        if ($method->executableLines === 0) {
            continue;
        }

        $totalMethods++;

        if ((float) $method->coverage === 100.0) {
            $coveredMethods++;

            continue;
        }

        $uncoveredMethods[] = [
            'name' => $method->methodName,
            'coverage' => (float) $method->coverage,
            'executedLines' => $method->executedLines,
            'executableLines' => $method->executableLines,
            'startLine' => $method->startLine,
        ];
    }

    usort(
        $uncoveredMethods,
        static fn (array $a, array $b): int => $a['startLine'] <=> $b['startLine'],
    );

    return [
        'coveredMethods' => $coveredMethods,
        'totalMethods' => $totalMethods,
        'uncoveredMethods' => $uncoveredMethods,
    ];
}

/**
 * @param  list<array{executableLines: int, executedLines: int}>  $uncoveredMethods
 */
function uncoveredLineCount(array $uncoveredMethods): int
{
    $lines = 0;

    foreach ($uncoveredMethods as $method) {
        $lines += $method['executableLines'] - $method['executedLines'];
    }

    return $lines;
}

function formatCoverage(float $coverage): string
{
    if ($coverage === 100.0) {
        return '100.0';
    }

    return number_format($coverage, 1, '.', '');
}

function repoRelativePath(string $absolutePath, string $repoRoot): string
{
    $normalizedRoot = rtrim(str_replace('\\', '/', $repoRoot), '/').'/';
    $normalizedPath = str_replace('\\', '/', $absolutePath);

    if (str_starts_with($normalizedPath, $normalizedRoot)) {
        return substr($normalizedPath, strlen($normalizedRoot));
    }

    return $normalizedPath;
}
