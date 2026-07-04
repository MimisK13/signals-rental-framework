<?php

use App\Services\Shortages\MoneyApportionment;

it('apportions remainder pennies to the largest fractional parts with ascending tie-break', function () {
    $result = (new MoneyApportionment)->apportion([
        10 => 1,
        20 => 1,
        30 => 1,
    ], 100);

    expect(array_sum($result))->toBe(100)
        ->and($result[10])->toBe(34)
        ->and($result[20])->toBe(33)
        ->and($result[30])->toBe(33);
});

it('returns empty allocations for empty weights', function () {
    expect((new MoneyApportionment)->apportion([], 500))->toBe([]);
});

it('returns zeros when all weights are zero', function () {
    expect((new MoneyApportionment)->apportion([1 => 0, 2 => 0], 500))
        ->toBe([1 => 0, 2 => 0]);
});

it('assigns the full total to a single participant', function () {
    expect((new MoneyApportionment)->apportion([42 => 99], 7500))
        ->toBe([42 => 7500]);
});
