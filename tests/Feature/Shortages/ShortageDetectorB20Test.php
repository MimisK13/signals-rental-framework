<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\ShortageResolutionType;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\VirtualStockDemandResolver;
use App\Services\Shortages\ShortageDetector;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->app->bind(AvailabilityResolutionProvider::class, fn (): object => new class implements AvailabilityResolutionProvider
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
 * @param  array<string, mixed>  $overrides
 */
function b20Opportunity(Store $store, array $overrides = []): Opportunity
{
    $data = (new CreateOpportunity)(CreateOpportunityData::from(array_merge([
        'subject' => 'B2.0 shortage context',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T17:00:00Z',
    ], $overrides)));

    return Opportunity::query()->whereKey($data->id)->firstOrFail();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function b20Item(Opportunity $opportunity, Product $product, string $quantity = '1', array $overrides = []): OpportunityItem
{
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from(array_merge([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => $quantity,
    ], $overrides)));

    return $opportunity->fresh(['items'])->items->last();
}

function b20Stock(Product $product, Store $store, int $quantity): StockLevel
{
    return StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => $quantity,
    ]);
}

function b20Demand(Product $product, Store $store, int $quantity, string $from = '2026-07-01T09:00:00Z', string $until = '2026-07-02T17:00:00Z'): Demand
{
    return Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse($from), Carbon::parse($until))
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => $quantity,
            'source_type' => $quantity < 0 ? 'virtual_stock_intake' : 'opportunity_item',
            'source_id' => random_int(100000, 999999),
            'metadata' => [],
        ]);
}

it('uses explicit opportunity planning dates for shortage detection', function (): void {
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $this->store, 0);
    b20Demand($product, $this->store, 5, '2026-07-10T09:00:00Z', '2026-07-12T17:00:00Z');

    $opportunity = b20Opportunity($this->store, [
        'planning_starts_at' => '2026-07-10T09:00:00Z',
        'planning_ends_at' => '2026-07-12T17:00:00Z',
    ]);
    $item = b20Item($opportunity, $product, '1');

    expect($this->detector->forItem($item, $opportunity)?->shortfall)->toBe(1);
});

it('uses an item planning override over the opportunity planning period', function (): void {
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $this->store, 5);
    b20Demand($product, $this->store, 5, '2026-07-10T09:00:00Z', '2026-07-12T17:00:00Z');

    $opportunity = b20Opportunity($this->store, [
        'planning_starts_at' => '2026-07-10T09:00:00Z',
        'planning_ends_at' => '2026-07-12T17:00:00Z',
    ]);
    $item = b20Item($opportunity, $product, '1', [
        'planning_starts_at' => '2026-07-20T09:00:00Z',
        'planning_ends_at' => '2026-07-22T17:00:00Z',
    ]);

    expect($this->detector->forItem($item, $opportunity))->toBeNull();
});

it('ignores charge dates when explicit planning dates exist', function (): void {
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $this->store, 5);
    b20Demand($product, $this->store, 5, '2026-07-10T09:00:00Z', '2026-07-12T17:00:00Z');

    $opportunity = b20Opportunity($this->store, [
        'planning_starts_at' => '2026-07-10T09:00:00Z',
        'planning_ends_at' => '2026-07-12T17:00:00Z',
        'charge_starts_at' => '2026-07-20T09:00:00Z',
        'charge_ends_at' => '2026-07-22T17:00:00Z',
    ]);
    $item = b20Item($opportunity, $product, '1');

    expect($this->detector->forItem($item, $opportunity)?->shortfall)->toBe(1);
});

it('keeps legacy records on the legacy operational date fallback', function (): void {
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $this->store, 0);

    $opportunity = b20Opportunity($this->store);
    $item = b20Item($opportunity, $product, '1');

    expect($opportunity->planning_starts_at)->toBeNull()
        ->and($opportunity->planning_ends_at)->toBeNull()
        ->and($this->detector->forItem($item, $opportunity)?->shortfall)->toBe(1)
        ->and($this->detector->forItem($item, $opportunity)?->startsAt->toIso8601String())
        ->toBe('2026-07-01T09:00:00+00:00');
});

it('uses the opportunity store when no item dispatch store override exists', function (): void {
    $otherStore = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $otherStore, 5);

    $opportunity = b20Opportunity($this->store);
    $item = b20Item($opportunity, $product, '1');

    expect($this->detector->forItem($item, $opportunity)?->shortfall)->toBe(1);
});

it('uses the item dispatch store override for shortage detection', function (): void {
    $dispatchStore = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $dispatchStore, 5);

    $opportunity = b20Opportunity($this->store);
    $item = b20Item($opportunity, $product, '1', ['dispatch_store_id' => $dispatchStore->id]);

    $item->forceFill(['dispatch_store_id' => $dispatchStore->id])->saveQuietly();
    app(OpportunityItemDemandResolver::class)->syncDemands($item->refresh());

    expect($this->detector->forItem($item, $opportunity))->toBeNull();
});

it('matches the Demand planning and Store context used by shortage detection', function (): void {
    $dispatchStore = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $dispatchStore, 1);

    $opportunity = b20Opportunity($this->store, [
        'planning_starts_at' => '2026-07-10T09:00:00Z',
        'planning_ends_at' => '2026-07-12T17:00:00Z',
    ]);
    $item = b20Item($opportunity, $product, '1', [
        'planning_starts_at' => '2026-07-20T09:00:00Z',
        'planning_ends_at' => '2026-07-22T17:00:00Z',
    ]);
    $item->forceFill(['dispatch_store_id' => $dispatchStore->id])->saveQuietly();
    app(OpportunityItemDemandResolver::class)->syncDemands($item->refresh());

    $demand = Demand::query()->where('source_type', 'opportunity_item')->where('source_id', $item->id)->sole();

    expect($demand->store_id)->toBe($dispatchStore->id)
        ->and($demand->starts_at->toIso8601String())->toBe('2026-07-20T09:00:00+00:00')
        ->and($demand->ends_at->toIso8601String())->toBe('2026-07-22T17:00:00+00:00')
        ->and($this->detector->forItem($item, $opportunity))->toBeNull();
});

it('counts confirmed external supply through live VirtualStock Demand exactly once', function (): void {
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $this->store, 6);
    b20Demand($product, $this->store, -4);

    $opportunity = b20Opportunity($this->store);
    $item = b20Item($opportunity, $product, '10');

    expect($this->detector->forItem($item, $opportunity))->toBeNull();
});

it('uses the confirmed VirtualStock intake demand as the external supply source', function (): void {
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $this->store, 6);

    $intake = VirtualStockIntake::factory()->confirmed()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity' => 4,
        'available_from' => '2026-07-01T09:00:00Z',
        'available_until' => '2026-07-02T17:00:00Z',
    ]);
    $supplyDemand = b20Demand($product, $this->store, 0);
    $supplyDemand->update([
        'source_type' => 'virtual_stock_intake',
        'source_id' => $intake->id,
        'is_active' => false,
        'phase' => DemandPhase::Void->value,
    ]);
    $intake->update(['demand_id' => $supplyDemand->id]);

    app(VirtualStockDemandResolver::class)->syncDemands($intake->fresh());

    $opportunity = b20Opportunity($this->store);
    $item = b20Item($opportunity, $product, '10');

    expect($supplyDemand->refresh()->quantity)->toBe(-4)
        ->and($this->detector->forItem($item, $opportunity))->toBeNull();
});

it('counts only the confirmed external quantity in live shortage coverage', function (): void {
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $this->store, 6);
    b20Demand($product, $this->store, -2);

    $opportunity = b20Opportunity($this->store);
    $item = b20Item($opportunity, $product, '10');

    expect($this->detector->forItem($item, $opportunity)?->shortfall)->toBe(2);
});

it('does not treat a sub-hire resolution or purchase order as live supply', function (): void {
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $this->store, 6);

    $opportunity = b20Opportunity($this->store);
    $item = b20Item($opportunity, $product, '10');
    $resolution = ShortageResolution::factory()->create([
        'resolution_type' => ShortageResolutionType::Subhire->value,
        'quantity_resolved' => 4,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $item->id,
        'quantity_allocated' => 4,
    ]);

    expect($this->detector->forItem($item, $opportunity)?->shortfall)->toBe(4);
});

it('does not subtract sub-hire provenance a second time when live supply exists', function (): void {
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $this->store, 6);
    b20Demand($product, $this->store, -2);

    $opportunity = b20Opportunity($this->store);
    $item = b20Item($opportunity, $product, '10');
    $resolution = ShortageResolution::factory()->create([
        'resolution_type' => ShortageResolutionType::Subhire->value,
        'quantity_resolved' => 2,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $item->id,
        'quantity_allocated' => 2,
    ]);

    expect($this->detector->forItem($item, $opportunity)?->remainingShortfall())->toBe(2);
});

it('does not over-cover a shortage when confirmed supply has matching sub-hire provenance', function (): void {
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $this->store, 6);
    b20Demand($product, $this->store, -4);

    $opportunity = b20Opportunity($this->store);
    $item = b20Item($opportunity, $product, '10');
    $resolution = ShortageResolution::factory()->create([
        'resolution_type' => ShortageResolutionType::Subhire->value,
        'quantity_resolved' => 4,
    ]);
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $item->id,
        'quantity_allocated' => 4,
    ]);

    expect($this->detector->forItem($item, $opportunity))->toBeNull();
});

it('restores the shortage after confirmed external supply is removed', function (): void {
    $product = Product::factory()->rental()->bulk()->create();
    b20Stock($product, $this->store, 6);
    $supply = b20Demand($product, $this->store, -4);

    $opportunity = b20Opportunity($this->store);
    $item = b20Item($opportunity, $product, '10');

    expect($this->detector->forItem($item, $opportunity))->toBeNull();

    $supply->update([
        'phase' => DemandPhase::Void->value,
        'is_active' => false,
    ]);

    expect($this->detector->forItem($item->fresh(), $opportunity))->not->toBeNull()
        ->and($this->detector->forItem($item->fresh(), $opportunity)?->shortfall)->toBe(4);
});
