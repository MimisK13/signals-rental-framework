<?php

use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Shortages\ShortageCrossOpportunityScanner;
use App\Services\Shortages\ShortageDetector;
use App\ValueObjects\ShortageCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('batches peer items within an opportunity while preserving windows and identity', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 0,
    ]);

    $localOpportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-08-01 09:00:00'),
        'ends_at' => Carbon::parse('2026-08-03 17:00:00'),
    ]);
    $peerOpportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-08-01 09:00:00'),
        'ends_at' => Carbon::parse('2026-08-03 17:00:00'),
    ]);

    $localItem = OpportunityItem::factory()->for($localOpportunity)->create([
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => '1',
    ]);
    $firstPeerItem = OpportunityItem::factory()->for($peerOpportunity)->create([
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => '2',
        'starts_at' => Carbon::parse('2026-08-05 09:00:00'),
        'ends_at' => Carbon::parse('2026-08-06 17:00:00'),
        'path' => '0001',
    ]);
    $secondPeerItem = OpportunityItem::factory()->for($peerOpportunity)->create([
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => '3',
        'starts_at' => Carbon::parse('2026-08-10 09:00:00'),
        'ends_at' => Carbon::parse('2026-08-11 17:00:00'),
        'path' => '0002',
    ]);

    $localShortage = app(ShortageDetector::class)->forItem($localItem, $localOpportunity);

    $queries = [
        'stock' => 0,
        'demand' => 0,
    ];

    DB::listen(function ($query) use (&$queries): void {
        $sql = strtolower($query->sql);

        if (str_contains($sql, 'stock_category as category') && str_contains($sql, 'from "stock_levels"')) {
            $queries['stock']++;
        }

        if (str_contains($sql, 'from "demands"')
            && str_contains($sql, 'product_id')
            && str_contains($sql, 'store_id')
            && str_contains($sql, 'is_active')) {
            $queries['demand']++;
        }
    });

    $merged = app(ShortageCrossOpportunityScanner::class)->withPeerShortages(
        $localOpportunity,
        new ShortageCollection([$localShortage]),
    );

    $peerShortages = $merged->slice(1)->values();

    expect($merged)->toHaveCount(3)
        ->and($peerShortages->pluck('opportunityItemId')->all())->toBe([
            $firstPeerItem->id,
            $secondPeerItem->id,
        ])
        ->and($peerShortages->pluck('opportunityId')->unique()->all())->toBe([$peerOpportunity->id])
        ->and($peerShortages->pluck('shortfall')->all())->toBe([2, 3])
        ->and($queries['stock'])->toBe(1)
        ->and($queries['demand'])->toBe(2);
});

it('preserves each peer item self-demand exclusion inside a batch', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 3,
    ]);

    $from = Carbon::parse('2026-09-01 09:00:00');
    $to = Carbon::parse('2026-09-03 17:00:00');
    $localOpportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => $from,
        'ends_at' => $to,
    ]);
    $peerOpportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => $from,
        'ends_at' => $to,
    ]);

    $localItem = OpportunityItem::factory()->for($localOpportunity)->create([
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => '4',
        'starts_at' => $from,
        'ends_at' => $to,
    ]);
    $firstPeerItem = OpportunityItem::factory()->for($peerOpportunity)->create([
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => '2',
        'starts_at' => $from,
        'ends_at' => $to,
        'path' => '0001',
    ]);
    $secondPeerItem = OpportunityItem::factory()->for($peerOpportunity)->create([
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => '2',
        'starts_at' => $from,
        'ends_at' => $to,
        'path' => '0002',
    ]);

    $localShortage = app(ShortageDetector::class)->forItem($localItem, $localOpportunity);

    Demand::factory()->window($from, $to)->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity' => 2,
        'source_type' => 'opportunity_item',
        'source_id' => $firstPeerItem->id,
    ]);
    Demand::factory()->window($from, $to)->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity' => 2,
        'source_type' => 'opportunity_item',
        'source_id' => $secondPeerItem->id,
    ]);

    $queries = [
        'stock' => 0,
        'demand' => 0,
    ];

    DB::listen(function ($query) use (&$queries): void {
        $sql = strtolower($query->sql);

        if (str_contains($sql, 'stock_category as category') && str_contains($sql, 'from "stock_levels"')) {
            $queries['stock']++;
        }

        if (str_contains($sql, 'from "demands"')
            && str_contains($sql, 'product_id')
            && str_contains($sql, 'store_id')
            && str_contains($sql, 'is_active')) {
            $queries['demand']++;
        }
    });

    $merged = app(ShortageCrossOpportunityScanner::class)->withPeerShortages(
        $localOpportunity,
        new ShortageCollection([$localShortage]),
    );
    $peerShortages = $merged->slice(1)->values();

    expect($peerShortages->pluck('opportunityItemId')->all())->toBe([
        $firstPeerItem->id,
        $secondPeerItem->id,
    ])
        ->and($peerShortages->pluck('shortfall')->all())->toBe([1, 1])
        ->and($queries['stock'])->toBe(1)
        ->and($queries['demand'])->toBe(1);
});
