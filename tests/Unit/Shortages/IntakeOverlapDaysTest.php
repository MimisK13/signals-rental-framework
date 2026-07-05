<?php

use App\Services\Shortages\IntakeOverlapDays;
use Illuminate\Support\Carbon;

it('counts half-open overlap days between intake and opportunity windows', function () {
    $counter = new IntakeOverlapDays;

    $days = $counter->count(
        Carbon::parse('2026-08-01T00:00:00Z'),
        Carbon::parse('2026-08-06T00:00:00Z'),
        Carbon::parse('2026-08-02T09:00:00Z'),
        Carbon::parse('2026-08-04T17:00:00Z'),
    );

    expect($days)->toBe(3);
});

it('returns zero for adjacent non-overlapping half-open windows', function () {
    $counter = new IntakeOverlapDays;

    $days = $counter->count(
        Carbon::parse('2026-08-01T00:00:00Z'),
        Carbon::parse('2026-08-05T00:00:00Z'),
        Carbon::parse('2026-08-05T00:00:00Z'),
        Carbon::parse('2026-08-10T00:00:00Z'),
    );

    expect($days)->toBe(0);
});

it('returns zero when opportunity window is entirely outside intake window', function () {
    $counter = new IntakeOverlapDays;

    $days = $counter->count(
        Carbon::parse('2026-08-01T00:00:00Z'),
        Carbon::parse('2026-08-06T00:00:00Z'),
        Carbon::parse('2026-08-10T00:00:00Z'),
        Carbon::parse('2026-08-12T00:00:00Z'),
    );

    expect($days)->toBe(0);
});
