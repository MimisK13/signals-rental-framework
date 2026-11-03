<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Availability\Contracts\AvailabilityResolutionProvider;
use App\Domain\Availability\Enums\AvailabilityResolution;
use App\Domain\Availability\Enums\DemandPhase;
use App\Domain\Availability\Models\Demand;
use App\Domain\Availability\Shortages\Models\ShortageResolution;
use App\Domain\Availability\Shortages\Models\ShortageResolutionItem;
use App\Domain\Availability\Shortages\Services\ShortageDetector;
use App\Domain\Availability\Shortages\ValueObjects\Shortage;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Stock\Enums\StockMethod;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Daily resolution so slot boundaries are deterministic, as in the
    // availability suite.
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->detector = app(ShortageDetector::class);
});

/**
 * Add a competing (active) demand from another booking that claims stock over the
 * window, with no asset id (bulk) by default.
 */
function competingDemand(int $productId, int $storeId, int $quantity, string $start, string $end): Demand
{
    return Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse($start), Carbon::parse($end))
        ->create([
            'product_id' => $productId,
            'store_id' => $storeId,
            'quantity' => $quantity,
            'source_type' => 'opportunity_item',
            'source_id' => 999000 + random_int(1, 999),
            'metadata' => [],
        ]);
}

function quotationWithItem(Store $store, Product $product, string $quantity): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Shortage test',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => $quantity,
    ]));

    return $opportunity->fresh(['items']);
}

it('detects a bulk shortage with the correct shortfall', function () {
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);

    // 4 units already committed elsewhere; only 1 free. The opportunity wants 3.
    competingDemand($product->id, $this->store->id, 4, '2026-07-01T09:00:00Z', '2026-07-05T17:00:00Z');

    $opportunity = quotationWithItem($this->store, $product, '3');

    $shortages = $this->detector->forOpportunity($opportunity);

    expect($shortages)->toHaveCount(1);

    /** @var Shortage $shortage */
    $shortage = $shortages->first();

    expect($shortage->productId)->toBe($product->id)
        ->and($shortage->requestedQuantity)->toBe(3)
        ->and($shortage->availableQuantity)->toBe(1)
        ->and($shortage->shortfall)->toBe(2)
        ->and($shortage->remainingShortfall())->toBe(2)
        ->and($shortage->trackingType)->toBe(StockMethod::Bulk)
        ->and($shortage->isCritical)->toBeFalse();
});

it('reports no shortage when stock is sufficient', function () {
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);

    $opportunity = quotationWithItem($this->store, $product, '3');

    expect($this->detector->forOpportunity($opportunity))->toBeEmpty();
});

it('does not count the item against its own demand', function () {
    // Stock exactly meets the request; the item's own demand must not make it
    // appear short against itself.
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 3,
    ]);

    $opportunity = quotationWithItem($this->store, $product, '3');

    expect($this->detector->forOpportunity($opportunity))->toBeEmpty();
});

it('detects a serialised shortage by free-asset count', function () {
    $product = Product::factory()->rental()->serialised()->create();
    // Only 2 serialised assets exist; the line wants 4.
    StockLevel::factory()->serialised()->count(2)->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    $opportunity = quotationWithItem($this->store, $product, '4');

    $shortages = $this->detector->forOpportunity($opportunity);

    expect($shortages)->toHaveCount(1);

    /** @var Shortage $shortage */
    $shortage = $shortages->first();

    expect($shortage->requestedQuantity)->toBe(4)
        ->and($shortage->availableQuantity)->toBe(2)
        ->and($shortage->shortfall)->toBe(2)
        ->and($shortage->trackingType)->toBe(StockMethod::Serialised);
});

it('nets active resolutions off the remaining shortfall', function () {
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);
    competingDemand($product->id, $this->store->id, 4, '2026-07-01T09:00:00Z', '2026-07-05T17:00:00Z');

    $opportunity = quotationWithItem($this->store, $product, '3');
    /** @var OpportunityItem $item */
    $item = $opportunity->items->first();

    // Record a resolution covering 1 of the 2 short units.
    $resolution = ShortageResolution::factory()->create(['quantity_resolved' => 1]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $item->id,
        'quantity_allocated' => 1,
    ]);

    /** @var Shortage $shortage */
    $shortage = $this->detector->forItem($item->fresh(), $opportunity);

    expect($shortage->shortfall)->toBe(2)
        ->and($shortage->remainingShortfall())->toBe(1);
});

it('ignores non-product line items', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Service only',
        'store_id' => $this->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Ad-hoc labour',
        'quantity' => '5',
    ]));

    expect($this->detector->forOpportunity($opportunity->fresh(['items'])))->toBeEmpty();
});
