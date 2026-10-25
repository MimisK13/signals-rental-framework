<?php

use App\Data\Opportunities\PreparedProductInputs;
use App\Data\Opportunities\PreparedStandardDemandInputs;
use App\Data\Opportunities\PreparedStockInputs;
use App\Models\Demand;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Services\Availability\OwnedCapacityReader;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

it('uses prepared raw Demand while excluding each item independently', function (): void {
    $from = Carbon::parse('2026-10-17T09:00:00Z');
    $to = Carbon::parse('2026-10-20T17:00:00Z');
    $product = new Product;
    $product->setAttribute('id', 33);
    $itemA = new OpportunityItem(['id' => 101]);
    $itemB = new OpportunityItem(['id' => 102]);
    $context = [
        'product_id' => 33,
        'store_id' => 1,
        'from' => $from,
        'to' => $to,
    ];
    $rawDemands = new Collection([
        new Demand(['source_type' => 'opportunity_item', 'source_id' => 101, 'quantity' => 2]),
        new Demand(['source_type' => 'opportunity_item', 'source_id' => 102, 'quantity' => 3]),
        new Demand(['source_type' => 'external', 'source_id' => 900, 'quantity' => 4]),
    ]);
    $pipeline = Mockery::mock(RecalculationPipeline::class);

    $reader = new OwnedCapacityReader($pipeline);
    $results = $reader->availableForItems(
        [$itemA, $itemB],
        [101 => $context, 102 => $context],
        PreparedProductInputs::fromCollection(collect([$product])),
        new PreparedStockInputs([PreparedStockInputs::key(33, 1) => 10]),
        new PreparedStandardDemandInputs([
            PreparedStandardDemandInputs::key(33, 1, $from, $to) => $rawDemands,
        ]),
    );

    expect($results)->toBe([101 => 3, 102 => 4]);
});

it('does not query stock or Demand when prepared inputs are missing', function (): void {
    $from = Carbon::parse('2026-10-17T09:00:00Z');
    $to = Carbon::parse('2026-10-20T17:00:00Z');
    $item = new OpportunityItem(['id' => 101]);
    $context = [
        'product_id' => 33,
        'store_id' => 1,
        'from' => $from,
        'to' => $to,
    ];
    $product = new Product;
    $product->setAttribute('id', 33);
    $reader = new OwnedCapacityReader(Mockery::mock(RecalculationPipeline::class));

    expect(fn (): array => $reader->availableForItems(
        [$item],
        [101 => $context],
        PreparedProductInputs::fromCollection(collect([$product])),
        new PreparedStockInputs([]),
        new PreparedStandardDemandInputs([]),
    ))->toThrow(LogicException::class);
});
