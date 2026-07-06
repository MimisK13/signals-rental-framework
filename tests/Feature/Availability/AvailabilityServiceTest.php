<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\AvailabilityDailySummary;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Pin the resolution to Daily so slot boundaries are deterministic.
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->service = app(AvailabilityService::class);
});

/**
 * Create an active demand without going through the (auto-recalculating) factory
 * defaults, so the test controls product/store/window precisely.
 *
 * @param  array<string, mixed>  $metadata
 */
function makeDemand(int $productId, int $storeId, int $quantity, string $start, string $end, DemandPhase $phase = DemandPhase::Committed, array $metadata = []): Demand
{
    return Demand::factory()
        ->phase($phase)
        ->window(Carbon::parse($start), Carbon::parse($end))
        ->create([
            'product_id' => $productId,
            'store_id' => $storeId,
            'quantity' => $quantity,
            'metadata' => $metadata,
        ]);
}

describe('getAvailability (point, on-the-fly)', function () {
    it('subtracts active demand from bulk stock', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        makeDemand($product->id, $this->store->id, 3, '2026-04-10T09:00:00Z', '2026-04-12T17:00:00Z');

        $result = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-11T08:00:00Z'));

        expect($result->total_stock)->toBe(10)
            ->and($result->total_demanded)->toBe(3)
            ->and($result->available)->toBe(7)
            ->and($result->demand_breakdown)->toBe(['opportunity_item' => 3]);
    });

    it('counts serialised stock rows as one unit each', function () {
        $product = Product::factory()->serialised()->create();
        StockLevel::factory()->serialised()->count(4)->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        $result = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-11T08:00:00Z'));

        expect($result->total_stock)->toBe(4)
            ->and($result->available)->toBe(4);
    });

    it('ignores inactive (Draft/Void) demands', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        makeDemand($product->id, $this->store->id, 4, '2026-04-10T00:00:00Z', '2026-04-12T00:00:00Z', DemandPhase::Draft);
        makeDemand($product->id, $this->store->id, 2, '2026-04-10T00:00:00Z', '2026-04-12T00:00:00Z', DemandPhase::Void);

        $result = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-11T08:00:00Z'));

        expect($result->total_demanded)->toBe(0)
            ->and($result->available)->toBe(10);
    });

    it('does not count demand outside the queried slot', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        // Demand on 10-11 April; query 20 April.
        makeDemand($product->id, $this->store->id, 5, '2026-04-10T00:00:00Z', '2026-04-11T00:00:00Z');

        $result = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-20T08:00:00Z'));

        expect($result->total_demanded)->toBe(0)
            ->and($result->available)->toBe(10);
    });

    it('nets bulk demand against returned_quantity', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        makeDemand($product->id, $this->store->id, 6, '2026-04-10T00:00:00Z', '2026-04-12T00:00:00Z', DemandPhase::Committed, ['returned_quantity' => 2]);

        $result = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-11T08:00:00Z'));

        expect($result->total_demanded)->toBe(4)
            ->and($result->available)->toBe(6);
    });

    it('can report a negative availability (shortage)', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 2,
        ]);

        makeDemand($product->id, $this->store->id, 5, '2026-04-10T00:00:00Z', '2026-04-12T00:00:00Z');

        $result = $this->service->getAvailability($product->id, $this->store->id, Carbon::parse('2026-04-11T08:00:00Z'));

        expect($result->available)->toBe(-3);
    });
});

describe('checkAvailability', function () {
    beforeEach(function () {
        $this->product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 5,
        ]);
        makeDemand($this->product->id, $this->store->id, 2, '2026-04-10T00:00:00Z', '2026-04-13T00:00:00Z');
    });

    it('returns true when the requested quantity fits in every slot', function () {
        $ok = $this->service->checkAvailability(
            $this->product->id,
            $this->store->id,
            Carbon::parse('2026-04-10T00:00:00Z'),
            Carbon::parse('2026-04-13T00:00:00Z'),
            3,
        );

        expect($ok)->toBeTrue();
    });

    it('returns false when the requested quantity exceeds availability in a slot', function () {
        $ok = $this->service->checkAvailability(
            $this->product->id,
            $this->store->id,
            Carbon::parse('2026-04-10T00:00:00Z'),
            Carbon::parse('2026-04-13T00:00:00Z'),
            4,
        );

        expect($ok)->toBeFalse();
    });

    it('returns true at the exact availability boundary', function () {
        // 5 stock - 2 demanded = 3 available; requesting exactly 3 must pass.
        $ok = $this->service->checkAvailability(
            $this->product->id,
            $this->store->id,
            Carbon::parse('2026-04-10T00:00:00Z'),
            Carbon::parse('2026-04-13T00:00:00Z'),
            3,
        );

        expect($ok)->toBeTrue();
    });
});

describe('availableForItem reversed-window guard (bug #4)', function () {
    // The DateShiftResolver could hand availableForItem a reversed window (from >
    // to) when Carbon-3's signed diff produced a negative duration. On Postgres
    // that built `tstzrange(lower, upper)` with lower > upper and threw
    // SQLSTATE[22000]. availableForItem now normalises the bounds defensively so a
    // reversed window never reaches the DB — this guard is driver-agnostic.
    beforeEach(function () {
        $this->product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 8,
        ]);
        makeDemand($this->product->id, $this->store->id, 3, '2026-05-10T09:00:00Z', '2026-05-12T17:00:00Z');
    });

    it('returns the same figure for a reversed window as for its forward equivalent', function () {
        $from = Carbon::parse('2026-05-10T09:00:00Z');
        $to = Carbon::parse('2026-05-12T17:00:00Z');

        $forward = $this->service->availableForItem(
            $this->product->id, $this->store->id, $from, $to, 'opportunity_item', 999999,
        );

        // Same window, bounds swapped — must be normalised, not throw, not differ.
        $reversed = $this->service->availableForItem(
            $this->product->id, $this->store->id, $to, $from, 'opportunity_item', 999999,
        );

        expect($reversed)->toBe($forward)
            ->and($forward)->toBe(5); // 8 held - 3 demanded
    });

    it('does not throw for a reversed window', function () {
        $from = Carbon::parse('2026-05-10T09:00:00Z');
        $to = Carbon::parse('2026-05-12T17:00:00Z');

        expect(fn () => $this->service->availableForItem(
            $this->product->id, $this->store->id, $to, $from, 'opportunity_item', 999999,
        ))->not->toThrow(Throwable::class);
    });
});

describe('availableForItem virtual stock supply', function () {
    it('includes negative demand from virtual stock intakes in the worst slot', function () {
        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 1,
        ]);

        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(
                Carbon::parse('2026-07-01T09:00:00Z'),
                Carbon::parse('2026-07-05T17:00:00Z'),
            )
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => -2,
                'source_type' => 'virtual_stock_intake',
                'source_id' => 1,
            ]);

        $available = $this->service->availableForItem(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-07-01T09:00:00Z'),
            Carbon::parse('2026-07-05T17:00:00Z'),
            'opportunity_item',
            999999,
        );

        expect($available)->toBe(3);
    });
});

describe('deferred methods', function () {
    it('returns a store-wide shortage sweep (implemented in P2)', function () {
        // getShortages was a BadMethodCallException stub until P2 implemented the
        // daily-summary sweep; with no shortage summaries it returns an empty set.
        $result = $this->service->getShortages($this->store->id, Carbon::now(), Carbon::now()->addDay());

        expect($result)->toBeIterable()->toHaveCount(0);
    });

    it('composes kit availability from component snapshots (M5-3a)', function () {
        $kit = Product::factory()->kit()->create();

        // Two pool components: one with 6 available (qty 2/kit → 3 kits), one with
        // 5 available (qty 1/kit → 5 kits). MIN → 3 kits.
        $componentA = Product::factory()->bulk()->create();
        $componentB = Product::factory()->bulk()->create();

        SerialisedComponent::factory()->pool()->quantity(2)->create([
            'product_id' => $kit->id,
            'component_product_id' => $componentA->id,
        ]);
        SerialisedComponent::factory()->pool()->quantity(1)->create([
            'product_id' => $kit->id,
            'component_product_id' => $componentB->id,
        ]);

        $slot = Carbon::parse('2026-09-01T00:00:00Z');

        AvailabilitySnapshot::factory()->create([
            'product_id' => $componentA->id, 'store_id' => $this->store->id,
            'slot_start' => $slot, 'total_stock' => 6, 'total_demanded' => 0, 'available' => 6,
        ]);
        AvailabilitySnapshot::factory()->create([
            'product_id' => $componentB->id, 'store_id' => $this->store->id,
            'slot_start' => $slot, 'total_stock' => 5, 'total_demanded' => 0, 'available' => 5,
        ]);

        $range = $this->service->getKitAvailability(
            $kit->id,
            $this->store->id,
            $slot,
            $slot->copy()->addDay(),
        );

        expect($range->slots)->toHaveCount(1)
            ->and($range->slots[0]->available)->toBe(3)
            ->and($range->min_available)->toBe(3);
    });
});

describe('getCalendarForStores', function () {
    it('sums per-day availability across the requested stores', function () {
        $storeA = Store::factory()->create(['timezone' => 'UTC']);
        $storeB = Store::factory()->create(['timezone' => 'UTC']);
        $product = Product::factory()->bulk()->create();

        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $storeA->id,
            'quantity_held' => 10,
        ]);
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $storeB->id,
            'quantity_held' => 6,
        ]);

        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-06-20', 'UTC'), 4, 4)
            ->create(['product_id' => $product->id, 'store_id' => $storeA->id]);
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-06-20', 'UTC'), 2, 2)
            ->create(['product_id' => $product->id, 'store_id' => $storeB->id]);

        $calendar = $this->service->getCalendarForStores(
            [$storeA->id, $storeB->id],
            Carbon::parse('2026-06-20', 'UTC'),
            Carbon::parse('2026-06-20', 'UTC'),
            [$product->id],
        );

        $row = $calendar->first();

        expect($row)->not->toBeNull()
            ->and($row->days[0]->available)->toBe(6);

        $stockTotals = $this->service->productTotalStockForStores([$product->id], [$storeA->id, $storeB->id]);

        expect($stockTotals[$product->id])->toBe(16);
    });

    it('uses default-query stores when storeIds is empty', function () {
        $included = Store::factory()->create(['timezone' => 'UTC', 'include_in_default_queries' => true]);
        Store::factory()->create(['timezone' => 'UTC', 'include_in_default_queries' => false]);
        $product = Product::factory()->bulk()->create();

        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $included->id,
            'quantity_held' => 4,
        ]);

        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-06-20', 'UTC'), 2, 2)
            ->create(['product_id' => $product->id, 'store_id' => $included->id]);

        $calendar = $this->service->getCalendarForStores(
            [],
            Carbon::parse('2026-06-20', 'UTC'),
            Carbon::parse('2026-06-20', 'UTC'),
            [$product->id],
        );

        expect($calendar->first()?->days[0]->available)->toBe(2);
    });

    it('returns an empty collection when no stores resolve', function () {
        Store::factory()->create(['include_in_default_queries' => false]);

        $calendar = $this->service->getCalendarForStores(
            [],
            Carbon::parse('2026-06-20', 'UTC'),
            Carbon::parse('2026-06-20', 'UTC'),
            [Product::factory()->create()->id],
        );

        expect($calendar)->toBeEmpty();
    });

    it('flags shortage days when aggregated availability is negative', function () {
        $store = Store::factory()->create(['timezone' => 'UTC']);
        $product = Product::factory()->bulk()->create();

        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-06-20', 'UTC'), -2, -2)
            ->create([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'has_shortage' => false,
            ]);

        $row = $this->service->getCalendarForStores(
            [$store->id],
            Carbon::parse('2026-06-20', 'UTC'),
            Carbon::parse('2026-06-20', 'UTC'),
            [$product->id],
        )->first();

        expect($row?->days[0]->has_shortage)->toBeTrue();
    });

    it('returns an empty map when no product ids are requested', function () {
        expect($this->service->productTotalStockForStores([], [$this->store->id]))->toBe([]);
    });
});

describe('getGanttForStores', function () {
    it('merges demand bars and stock totals across stores', function () {
        $storeA = Store::factory()->create(['timezone' => 'UTC']);
        $storeB = Store::factory()->create(['timezone' => 'UTC']);
        $product = Product::factory()->bulk()->create();

        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $storeA->id,
            'quantity_held' => 3,
        ]);
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $storeB->id,
            'quantity_held' => 2,
        ]);

        $from = Carbon::parse('2026-08-10T00:00:00Z');
        $to = Carbon::parse('2026-08-12T00:00:00Z');

        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-08-10T09:00:00Z'), Carbon::parse('2026-08-11T17:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $storeA->id,
                'quantity' => 1,
                'source_type' => 'manual',
                'source_id' => 1,
            ]);
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-08-10T09:00:00Z'), Carbon::parse('2026-08-11T17:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $storeB->id,
                'quantity' => 1,
                'source_type' => 'manual',
                'source_id' => 2,
            ]);

        $gantt = $this->service->getGanttForStores($product->id, [$storeA->id, $storeB->id], $from, $to);

        expect($gantt->total_stock)->toBe(5)
            ->and($gantt->demands)->toHaveCount(2)
            ->and($gantt->store_id)->toBe($storeA->id);
    });

    it('returns an empty gantt when no stores resolve', function () {
        Store::factory()->create(['include_in_default_queries' => false]);
        $product = Product::factory()->bulk()->create();
        $from = Carbon::parse('2026-08-10T00:00:00Z');
        $to = Carbon::parse('2026-08-12T00:00:00Z');

        $gantt = $this->service->getGanttForStores($product->id, [], $from, $to);

        expect($gantt->total_stock)->toBe(0)
            ->and($gantt->demands)->toBe([])
            ->and($gantt->shortages)->toBe([]);
    });
});
