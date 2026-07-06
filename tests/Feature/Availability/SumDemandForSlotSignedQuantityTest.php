<?php

use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->pipeline = app(RecalculationPipeline::class);
});

it('subtracts negative effective quantities in sumDemandForSlot', function () {
    $slotStart = Carbon::parse('2026-05-01T00:00:00Z');
    $slotEnd = Carbon::parse('2026-05-02T00:00:00Z');

    $positive = Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window($slotStart, $slotEnd)
        ->create(['quantity' => 5]);

    $virtualSupply = Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window($slotStart, $slotEnd)
        ->create([
            'quantity' => -2,
            'source_type' => 'virtual_stock_intake',
        ]);

    [$total] = $this->pipeline->sumDemandForSlot(collect([$positive, $virtualSupply]), $slotStart, $slotEnd);

    expect($total)->toBe(3);
});

it('still skips zero effective quantity demands in sumDemandForSlot', function () {
    $slotStart = Carbon::parse('2026-05-01T00:00:00Z');
    $slotEnd = Carbon::parse('2026-05-02T00:00:00Z');

    $active = Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window($slotStart, $slotEnd)
        ->create(['quantity' => 2]);

    $fullyReturned = Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window($slotStart, $slotEnd)
        ->create([
            'quantity' => 5,
            'metadata' => ['returned_quantity' => 5],
        ]);

    [$total] = $this->pipeline->sumDemandForSlot(collect([$active, $fullyReturned]), $slotStart, $slotEnd);

    expect($total)->toBe(2);
});
