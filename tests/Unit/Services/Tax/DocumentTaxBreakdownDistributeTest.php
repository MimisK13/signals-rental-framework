<?php

use App\Data\Tax\DocumentTaxLineInput;
use App\Services\Tax\DocumentTaxBreakdown;

it('returns empty largest-remainder allocations for empty member sets', function () {
    $method = new ReflectionMethod(DocumentTaxBreakdown::class, 'distributeLargestRemainder');
    $method->setAccessible(true);

    expect($method->invoke(app(DocumentTaxBreakdown::class), [], 100))->toBe([]);
});

it('returns zero allocations when group tax is zero', function () {
    $method = new ReflectionMethod(DocumentTaxBreakdown::class, 'distributeLargestRemainder');
    $method->setAccessible(true);

    $members = [
        ['line' => new DocumentTaxLineInput(lineKey: 'a', netMinor: 100), 'netMinor' => 100],
        ['line' => new DocumentTaxLineInput(lineKey: 'b', netMinor: 200), 'netMinor' => 200],
    ];

    expect($method->invoke(app(DocumentTaxBreakdown::class), $members, 0))
        ->toBe(['a' => 0, 'b' => 0]);
});

it('returns zero allocations when total net weight is zero', function () {
    $method = new ReflectionMethod(DocumentTaxBreakdown::class, 'distributeLargestRemainder');
    $method->setAccessible(true);

    $members = [
        ['line' => new DocumentTaxLineInput(lineKey: 'a', netMinor: 0), 'netMinor' => 0],
        ['line' => new DocumentTaxLineInput(lineKey: 'b', netMinor: 0), 'netMinor' => 0],
    ];

    expect($method->invoke(app(DocumentTaxBreakdown::class), $members, 50))
        ->toBe(['a' => 0, 'b' => 0]);
});

it('distributes negative group tax using floor rounding for negative exact shares', function () {
    $method = new ReflectionMethod(DocumentTaxBreakdown::class, 'distributeLargestRemainder');
    $method->setAccessible(true);

    $members = [
        ['line' => new DocumentTaxLineInput(lineKey: 'a', netMinor: 1), 'netMinor' => 1],
        ['line' => new DocumentTaxLineInput(lineKey: 'b', netMinor: 1), 'netMinor' => 1],
        ['line' => new DocumentTaxLineInput(lineKey: 'c', netMinor: 1), 'netMinor' => 1],
    ];

    $allocations = $method->invoke(app(DocumentTaxBreakdown::class), $members, -1);

    expect(array_sum($allocations))->toBe(-1)
        ->and($allocations)->toHaveKeys(['a', 'b', 'c']);
});
