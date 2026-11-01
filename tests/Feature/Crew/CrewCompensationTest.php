<?php

declare(strict_types=1);

use App\Domain\Crew\Enums\CrewRateType;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Services\CrewCompensationCalculator;
use Illuminate\Validation\ValidationException;

it('calculates the documented pay examples using integer minor units', function (string $type, int $rate, string $start, string $end, int $break, string $minimum, int $minutes, int $pay): void {
    $assignment = CrewAssignment::factory()->make(['rate_type' => CrewRateType::from($type), 'rate_amount_minor' => $rate, 'min_call_hours' => $minimum, 'daily_call_hours' => '10']);
    $assignment->setRelation('request', CrewRequest::factory()->make(['timezone' => 'Europe/London']));
    $result = app(CrewCompensationCalculator::class)->calculate($assignment, [['id' => 1, 'started_at' => $start, 'ended_at' => $end, 'break_minutes' => $break]], ['weekend_multiplier' => '1.00']);

    expect($result['total_minutes'])->toBe($minutes);
    expect($result['total_pay_minor'])->toBe($pay);
})->with([
    'daily overtime after unpaid break' => ['hourly', 4500, '2026-11-02T09:00:00Z', '2026-11-02T19:00:00Z', 60, '4', 540, 42750],
    'call minimum' => ['hourly', 4500, '2026-11-02T09:00:00Z', '2026-11-02T11:30:00Z', 0, '4', 240, 18000],
    'daily excess' => ['daily', 35000, '2026-11-02T08:00:00Z', '2026-11-02T20:00:00Z', 0, '4', 720, 45500],
    'fixed' => ['fixed', 80000, '2026-11-02T09:00:00Z', '2026-11-02T11:00:00Z', 0, '4', 120, 80000],
    'night portion' => ['hourly', 4000, '2026-11-02T22:00:00Z', '2026-11-03T06:00:00Z', 0, '0', 480, 38000],
    'spring DST elapsed time' => ['hourly', 6000, '2026-03-28T22:00:00Z', '2026-03-29T05:00:00Z', 0, '0', 420, 49500],
]);

it('rounds each time entry according to the selected mode', function (string $mode, int $minutes, int $expected): void {
    expect(app(CrewCompensationCalculator::class)->roundMinutes($minutes, $mode, 15))->toBe($expected);
})->with([
    ['none', 7, 7], ['up', 7, 15], ['down', 14, 0], ['nearest', 7, 0], ['nearest', 8, 15], ['nearest', 15, 15],
]);

it('does not charge a fixed rate more than once for multiple entries', function (): void {
    $assignment = CrewAssignment::factory()->make(['rate_type' => CrewRateType::Fixed, 'rate_amount_minor' => 80000]);
    $assignment->setRelation('request', CrewRequest::factory()->make(['timezone' => 'UTC']));

    $result = app(CrewCompensationCalculator::class)->calculate($assignment, [
        ['id' => 1, 'started_at' => '2026-11-02T09:00:00Z', 'ended_at' => '2026-11-02T10:00:00Z'],
        ['id' => 2, 'started_at' => '2026-11-03T09:00:00Z', 'ended_at' => '2026-11-03T10:00:00Z'],
    ]);

    expect($result['total_pay_minor'])->toBe(80000);
});

it('removes daily overtime before calculating weekly overtime', function (): void {
    $assignment = CrewAssignment::factory()->make(['rate_amount_minor' => 6000]);
    $assignment->setRelation('request', CrewRequest::factory()->make(['timezone' => 'UTC']));
    $entries = [];
    foreach ([2, 3, 4, 5, 6] as $id => $day) {
        $entries[] = ['id' => $id, 'started_at' => "2026-11-0{$day}T08:00:00Z", 'ended_at' => "2026-11-0{$day}T18:00:00Z"];
    }

    $result = app(CrewCompensationCalculator::class)->calculate($assignment, $entries);

    expect($result['total_minutes'])->toBe(3000);
    expect($result['total_pay_minor'])->toBe(330000);
    expect(collect($result['breakdown'])->where('code', 'WEEKLY_OT'))->toHaveCount(0);
});

it('applies allowance currency and frequency without multiplying allowances', function (): void {
    $assignment = CrewAssignment::factory()->make(['rate_amount_minor' => 6000, 'allowances_snapshot' => [
        ['code' => 'KIT', 'amount_minor' => 1000, 'currency' => 'GBP', 'per' => 'assignment'],
        ['code' => 'PER_DIEM', 'amount_minor' => 5000, 'currency' => 'GBP', 'per' => 'day'],
    ]]);
    $assignment->setRelation('request', CrewRequest::factory()->make(['timezone' => 'UTC']));

    $result = app(CrewCompensationCalculator::class)->calculate($assignment, [['id' => 1, 'started_at' => '2026-11-02T09:00:00Z', 'ended_at' => '2026-11-02T19:00:00Z']]);

    expect($result['total_pay_minor'])->toBe(72000);
});

it('rejects a break longer than the elapsed interval', function (): void {
    $assignment = CrewAssignment::factory()->make();
    $assignment->setRelation('request', CrewRequest::factory()->make(['timezone' => 'UTC']));

    expect(function () use ($assignment): void {
        app(CrewCompensationCalculator::class)->calculate($assignment, [['started_at' => '2026-11-02T09:00:00Z', 'ended_at' => '2026-11-02T10:00:00Z', 'break_minutes' => 60]]);
    })->toThrow(ValidationException::class);
});

it('uses each shift timezone when classifying night minutes', function (): void {
    $assignment = CrewAssignment::factory()->make(['rate_amount_minor' => 6000]);
    $assignment->setRelation('request', CrewRequest::factory()->make(['timezone' => 'UTC']));

    $result = app(CrewCompensationCalculator::class)->calculate($assignment, [['id' => 1, 'started_at' => '2026-11-02T22:00:00Z', 'ended_at' => '2026-11-03T02:00:00Z', 'timezone' => 'Europe/Athens']]);

    expect($result['total_pay_minor'])->toBe(30000);
});

it('pays the agreed flat day fee for a partial weekend call', function (): void {
    $assignment = CrewAssignment::factory()->make(['rate_type' => CrewRateType::Daily, 'rate_amount_minor' => 35000, 'daily_call_hours' => '10']);
    $assignment->setRelation('request', CrewRequest::factory()->make(['timezone' => 'UTC']));

    $result = app(CrewCompensationCalculator::class)->calculate($assignment, [['id' => 1, 'started_at' => '2026-11-07T09:00:00Z', 'ended_at' => '2026-11-07T11:00:00Z']]);

    expect($result['total_pay_minor'])->toBe(35000);
});

it('applies a weekend premium to the call minimum uplift on that date', function (): void {
    $assignment = CrewAssignment::factory()->make(['rate_amount_minor' => 6000, 'min_call_hours' => '4']);
    $assignment->setRelation('request', CrewRequest::factory()->make(['timezone' => 'UTC']));

    $result = app(CrewCompensationCalculator::class)->calculate($assignment, [['id' => 1, 'started_at' => '2026-11-07T09:00:00Z', 'ended_at' => '2026-11-07T11:00:00Z']]);

    expect($result['total_pay_minor'])->toBe(30000);
});

it('freezes the travel rate and the uniform applied premium on each entry', function (): void {
    $assignment = CrewAssignment::factory()->make(['rate_amount_minor' => 6000, 'allowances_snapshot' => ['_policy' => ['travel_rate_minor' => 2000]]]);
    $assignment->setRelation('request', CrewRequest::factory()->make(['timezone' => 'UTC']));

    $result = app(CrewCompensationCalculator::class)->calculate($assignment, [['id' => 1, 'started_at' => '2026-11-02T00:00:00Z', 'ended_at' => '2026-11-02T02:00:00Z', 'kind' => 'travel']]);

    expect($result['total_pay_minor'])->toBe(5000);
    expect($result['entries'][1]['applied_rate_minor'])->toBe(2000);
    expect($result['entries'][1]['multiplier'])->toBe('1.25');
});
