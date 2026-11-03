<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\AllocateAsset;
use App\Domain\Projects\Opportunities\Actions\ConvertToOrder;
use App\Domain\Projects\Opportunities\Actions\ConvertToQuotation;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\QuickBookOut;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\AllocateAssetData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\QuickBookOutData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Enums\LineItemTransactionType;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->rental()->serialised()->create();
});

/**
 * Build an event-sourced Order with a single serialised line, allocate one asset,
 * and return the allocated assignment row (ready to book out).
 */
function bookableAssetRow(Store $store, Product $product): OpportunityItemAsset
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Bookable',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    (new ConvertToOrder)($opportunity->refresh());

    /** @var OpportunityItem $item */
    $item = $opportunity->items()->firstOrFail();
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    return OpportunityItemAsset::query()
        ->where('opportunity_item_id', $item->id)
        ->where('stock_level_id', $asset->id)
        ->sole();
}

it('rejects a quick book-out asset that belongs to a different opportunity (404)', function () {
    // Target order whose book-out we attempt.
    $targetRow = bookableAssetRow($this->store, $this->product);
    $targetOpportunity = $targetRow->item->opportunity()->firstOrFail();

    // A foreign order's allocated asset row — belongs to a DIFFERENT opportunity.
    $foreignRow = bookableAssetRow($this->store, $this->product);

    expect($foreignRow->item->opportunity_id)->not->toBe($targetOpportunity->id);

    expect(fn () => (new QuickBookOut)($targetOpportunity->refresh(), QuickBookOutData::from([
        'asset_ids' => [$foreignRow->id],
    ])))->toThrow(NotFoundHttpException::class, 'does not belong to the opportunity');
});
