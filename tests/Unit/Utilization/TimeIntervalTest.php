<?php

use App\Support\Intervals\TimeInterval;
use Carbon\CarbonImmutable;

function interval(string $start, string $end): TimeInterval
{
    return new TimeInterval(CarbonImmutable::parse($start, 'UTC'), CarbonImmutable::parse($end, 'UTC'));
}

it('uses half-open intervals and unions adjacent ranges', function (): void {
    $first = interval('2026-01-01 10:00:00', '2026-01-01 12:00:00');
    $second = interval('2026-01-01 12:00:00', '2026-01-01 14:00:00');

    expect($first->intersection($second))->toBeNull()
        ->and(TimeInterval::totalSeconds([$first, $second]))->toBe(14400);
});

it('clips and subtracts intervals without double counting', function (): void {
    $source = interval('2026-01-01 00:00:00', '2026-01-01 10:00:00');
    $blocked = interval('2026-01-01 03:00:00', '2026-01-01 07:00:00');

    expect($source->clippedTo(
        CarbonImmutable::parse('2026-01-01 02:00:00', 'UTC'),
        CarbonImmutable::parse('2026-01-01 09:00:00', 'UTC'),
    )->seconds())->toBe(25200)
        ->and(TimeInterval::totalSeconds($source->subtract([$blocked])))->toBe(21600);
});

test('example', function () {
    expect(true)->toBeTrue();
});
