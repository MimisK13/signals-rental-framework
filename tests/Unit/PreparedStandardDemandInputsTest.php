<?php

use App\Data\Opportunities\PreparedStandardDemandInputs;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

it('isolates windows and stores in the semantic key', function (): void {
    $from = Carbon::parse('2026-10-17T09:00:00Z');
    $to = Carbon::parse('2026-10-20T17:00:00Z');
    $inputs = new PreparedStandardDemandInputs([
        PreparedStandardDemandInputs::key(33, 1, $from, $to) => new Collection(['a']),
        PreparedStandardDemandInputs::key(33, 2, $from, $to) => new Collection(['b']),
    ]);

    expect($inputs->required(33, 1, $from, $to)->all())->toBe(['a'])
        ->and($inputs->required(33, 2, $from, $to)->all())->toBe(['b'])
        ->and($inputs->has(33, 1, $from->copy()->addDay(), $to))->toBeFalse();
});

it('returns a fresh collection wrapper for consumer-specific filtering', function (): void {
    $from = Carbon::parse('2026-10-17T09:00:00Z');
    $to = Carbon::parse('2026-10-20T17:00:00Z');
    $inputs = new PreparedStandardDemandInputs([
        PreparedStandardDemandInputs::key(33, 1, $from, $to) => new Collection(['a', 'b']),
    ]);

    $first = $inputs->required(33, 1, $from, $to);
    $first->shift();

    expect($inputs->required(33, 1, $from, $to)->all())->toBe(['a', 'b']);
});

it('fails explicitly when a prepared demand key is missing', function (): void {
    $inputs = new PreparedStandardDemandInputs([]);
    $from = Carbon::parse('2026-10-17T09:00:00Z');
    $to = Carbon::parse('2026-10-20T17:00:00Z');

    expect(fn (): Collection => $inputs->required(33, 1, $from, $to))
        ->toThrow(LogicException::class);
});
