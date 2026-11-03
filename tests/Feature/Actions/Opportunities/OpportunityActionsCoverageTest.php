<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Currency\Services\CurrencyService;
use App\Domain\Products\Models\Accessory;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\Concerns\RebuildsAvailabilitySnapshots;
use App\Domain\Projects\Opportunities\Actions\ConvertToQuotation;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\CreateVersion;
use App\Domain\Projects\Opportunities\Actions\MergeOpportunityItems;
use App\Domain\Projects\Opportunities\Actions\QuickAllocateAssets;
use App\Domain\Projects\Opportunities\Actions\UpdateOpportunityItemDetails;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\CreateVersionData;
use App\Domain\Projects\Opportunities\Data\MergeOpportunityItemsData;
use App\Domain\Projects\Opportunities\Data\QuickAllocateAssetsData;
use App\Domain\Projects\Opportunities\Data\UpdateOpportunityItemDetailsData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

/**
 * Create an event-sourced opportunity with a live Verbs state.
 */
function coverageOpportunity(Store $store): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Coverage',
        'store_id' => $store->id,
        'starts_at' => '2026-08-01T09:00:00Z',
        'ends_at' => '2026-08-05T17:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

// ---------------------------------------------------------------------------
// AddOpportunityItem:189 — included accessory whose product is gone is skipped
// ---------------------------------------------------------------------------

it('skips an included accessory whose catalogue product has been soft-deleted', function () {
    $opportunity = coverageOpportunity($this->store);
    $product = Product::factory()->create(['name' => 'Mixer']);
    $gone = Product::factory()->create(['name' => 'Discontinued cable']);
    $present = Product::factory()->create(['name' => 'Live cable']);

    Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $gone->id,
        'quantity' => '1',
        'included' => true,
        'sort_order' => 1,
    ]);
    Accessory::factory()->create([
        'product_id' => $product->id,
        'accessory_product_id' => $present->id,
        'quantity' => '1',
        'included' => true,
        'sort_order' => 2,
    ]);

    // Soft-delete the first accessory's product: the belongsTo now resolves to null,
    // so its materialisation iteration hits the null-product `continue`.
    $gone->delete();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $items = $opportunity->refresh()->items()->orderBy('path')->get();

    // Principal + the one surviving accessory only (the deleted one was skipped).
    expect($items)->toHaveCount(2)
        ->and($items->pluck('itemable_id')->all())->toBe([$product->id, $present->id]);
});

// ---------------------------------------------------------------------------
// CreateOpportunity:152 — currency falls back to the company base currency
// ---------------------------------------------------------------------------

it('resolves the company base currency when none is supplied on create', function () {
    $base = app(CurrencyService::class)->baseCurrencyCode();

    // An empty currency string forces resolveCurrency() onto its base-currency
    // fallback (the DTO default is 'GBP', which would short-circuit before it).
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'No currency',
        'store_id' => $this->store->id,
        'currency' => '',
        'starts_at' => '2026-08-01T09:00:00Z',
        'ends_at' => '2026-08-05T17:00:00Z',
    ]));

    expect(Opportunity::query()->whereKey($created->id)->value('currency_code'))->toBe($base);
});

// ---------------------------------------------------------------------------
// CreateVersion:131 — explicit, valid source_version_id is returned
// ---------------------------------------------------------------------------

it('seeds a new version from an explicit valid source_version_id', function () {
    $opportunity = coverageOpportunity($this->store);
    $product = Product::factory()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '2',
        'unit_price' => 1500,
    ]));

    (new ConvertToQuotation)($opportunity->refresh());

    // First version becomes the active version carrying the line.
    $first = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));

    // A second version that explicitly names the first as its source.
    $second = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([
        'source_version_id' => $first->id,
    ]));

    expect($second->id)->not->toBe($first->id)
        ->and(OpportunityItem::query()->where('version_id', $second->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// UpdateOpportunityItemDetails:30 — a supplied (non-Optional) description is used
// ---------------------------------------------------------------------------

it('applies a supplied description and leaves omitted notes untouched', function () {
    $opportunity = coverageOpportunity($this->store);
    $product = Product::factory()->create();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 1000,
        'description' => 'original description',
        'notes' => 'original notes',
    ]));

    $item = $opportunity->refresh()->items()->firstOrFail();

    $result = (new UpdateOpportunityItemDetails)($item, UpdateOpportunityItemDetailsData::from([
        'description' => 'updated description',
    ]));

    expect($result->description)->toBe('updated description')
        ->and($item->refresh()->description)->toBe('updated description')
        // notes was omitted (Optional) → preserved.
        ->and($item->refresh()->notes)->toBe('original notes');

    // Now update ONLY notes: the omitted description takes the Optional branch and
    // is preserved from the item's current value.
    $second = (new UpdateOpportunityItemDetails)($item->refresh(), UpdateOpportunityItemDetailsData::from([
        'notes' => 'updated notes',
    ]));

    expect($second->notes)->toBe('updated notes')
        // description was omitted (Optional) → kept its previous value.
        ->and($item->refresh()->description)->toBe('updated description');
});

// ---------------------------------------------------------------------------
// QuickAllocateAssets:62 — a line item from a different opportunity 404s
// ---------------------------------------------------------------------------

it('rejects quick-allocating against a line item of a different opportunity', function () {
    $serialised = Product::factory()->rental()->serialised()->create();

    $opportunity = coverageOpportunity($this->store);

    // A line item belonging to a SECOND, unrelated opportunity.
    $other = coverageOpportunity($this->store);
    (new AddOpportunityItem)($other, AddOpportunityItemData::from([
        'name' => $serialised->name,
        'itemable_id' => $serialised->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    $foreignItem = $other->refresh()->items()->firstOrFail();

    $level = StockLevel::factory()->serialised()->create([
        'product_id' => $serialised->id,
        'store_id' => $this->store->id,
    ]);

    expect(fn () => (new QuickAllocateAssets)($opportunity->refresh(), QuickAllocateAssetsData::from([
        'allocations' => [
            ['opportunity_item_id' => $foreignItem->id, 'stock_level_id' => $level->id],
        ],
    ])))->toThrow(NotFoundHttpException::class);
});

// ---------------------------------------------------------------------------
// MergeOpportunityItems:140-143 — richest duplicate's discount is copied to survivor
// ---------------------------------------------------------------------------

it('copies the richest duplicate\'s discount onto the survivor when merging', function () {
    $product = Product::factory()->create();
    $opportunity = coverageOpportunity($this->store);

    // Survivor: cheaper, no discount.
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    // Duplicate: more expensive (so it is the "richest") AND carries a discount.
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 5000,
        'discount_percent' => '15',
    ]));

    $items = $opportunity->refresh()->allItems()->orderBy('id')->get();
    $survivor = $items->first();
    $duplicate = $items->last();

    expect($survivor->discount_percent)->toBeNull();

    (new MergeOpportunityItems)($survivor, MergeOpportunityItemsData::from([
        'duplicate_item_ids' => [$duplicate->id],
    ]));

    $merged = $survivor->refresh();

    // Survivor absorbed the richest line's discount and unit price.
    expect($merged->discount_percent)->toBe('15.00')
        ->and((int) $merged->unit_price)->toBe(5000)
        ->and((float) $merged->quantity)->toBe(2.0);
});

// ---------------------------------------------------------------------------
// RebuildsAvailabilitySnapshots:61,74 — null product/store skipped; empty → no-op
// ---------------------------------------------------------------------------

it('skips items with no resolvable product/store and dispatches nothing when none resolve', function () {
    Queue::fake();

    $rebuilder = new class
    {
        use RebuildsAvailabilitySnapshots;

        /** @param iterable<OpportunityItem> $items */
        public function run(iterable $items): void
        {
            $this->rebuildSnapshotsForItems($items);
        }
    };

    // An item with no itemable (product) AND no store — both null → the `continue`.
    $orphan = new OpportunityItem(['itemable_id' => null, 'dispatch_store_id' => null]);
    $orphan->setRelation('opportunity', null);

    $rebuilder->run([$orphan]);

    // No product/store resolved → $seen stayed empty → early return, nothing queued.
    Queue::assertNothingPushed();
});
