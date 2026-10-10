<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AdjustBulkQuantity;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\CheckAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\DispatchBulkQuantity;
use App\Actions\Opportunities\FinaliseAssetReturnInspection;
use App\Actions\Opportunities\MarkAssetOnHire;
use App\Actions\Opportunities\QuickBookOut;
use App\Actions\Opportunities\QuickCheckIn;
use App\Actions\Opportunities\ReturnAsset;
use App\Actions\Opportunities\ReturnBulkQuantity;
use App\Actions\Opportunities\RevertAssetStatus;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\BulkAdjustData;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\BulkReturnData;
use App\Data\Opportunities\CheckAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\QuickBookOutData;
use App\Data\Opportunities\QuickCheckInData;
use App\Data\Opportunities\ReturnAssetData;
use App\Data\Opportunities\RevertAssetStatusData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityStatus;
use App\Models\ActionLog;
use App\Models\Asset;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\Repair;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\AvailabilityService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->rental()->serialised()->create();
});

/**
 * Build an event-sourced Order with a single serialised line for `$quantity`
 * units, returning the line item ready for fulfilment.
 */
function makeFulfilmentOrder(Store $store, Product $product, string $quantity = '2'): OpportunityItem
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Fulfilment',
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);

    // Items are added while still a quotation — an order must have at least one
    // line item to be confirmed (opportunity-lifecycle.md §12.1 convert guard).
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => $quantity,
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    (new ConvertToOrder)($opportunity->refresh());

    return $opportunity->items()->firstOrFail();
}

function makeAsset(Store $store, Product $product): StockLevel
{
    return StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
}

function allocateOne(OpportunityItem $item, StockLevel $asset): OpportunityItemAsset
{
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $asset->id]));

    return OpportunityItemAsset::query()
        ->where('opportunity_item_id', $item->id)
        ->where('stock_level_id', $asset->id)
        ->sole();
}

function makePhysicalAsset(Store $store, Product $product): StockLevel
{
    $physicalAsset = Asset::factory()->create(['product_id' => $product->id]);

    return StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_id' => $physicalAsset->id,
    ]);
}

it('runs the full dispatch -> on-hire -> return -> check happy path', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $asset = makeAsset($this->store, $this->product);
    $row = allocateOne($item, $asset);

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    expect($row->refresh()->status)->toBe(AssetAssignmentStatus::Dispatched)
        ->and($row->dispatched_at)->not->toBeNull();

    (new MarkAssetOnHire)($row->refresh());
    expect($row->refresh()->status)->toBe(AssetAssignmentStatus::OnHire);

    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([]));
    expect($row->refresh()->status)->toBe(AssetAssignmentStatus::CheckedIn)
        ->and($row->returned_at)->not->toBeNull();

    (new CheckAsset)($row->refresh(), CheckAssetData::from(['condition' => AssetCondition::Good->value]));
    expect($row->refresh()->status)->toBe(AssetAssignmentStatus::Finalised)
        ->and($row->condition_on_return)->toBe(AssetCondition::Good);
});

it('holds a checked-in asset from availability until inspection is finalised', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $asset = makeAsset($this->store, $this->product);
    $row = allocateOne($item, $asset);
    $from = Carbon::parse('2026-09-03T12:00:00Z');
    $to = $from->copy()->addDay();

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([
        'returned_at' => $from->toIso8601String(),
    ]));

    expect(Demand::query()
        ->where('source_type', 'inspection_hold')
        ->where('source_id', $row->id)
        ->where('is_active', true)
        ->count())->toBe(1)
        ->and(app(AvailabilityService::class)->getAvailableAssets($this->product->id, $this->store->id, $from, $to)
            ->pluck('id')->all())->not->toContain($asset->id);

    (new CheckAsset)($row->refresh(), CheckAssetData::from([
        'condition' => AssetCondition::Good->value,
    ]));

    expect(Demand::query()
        ->where('source_type', 'inspection_hold')
        ->where('source_id', $row->id)
        ->where('is_active', true)
        ->count())->toBe(0)
        ->and(app(AvailabilityService::class)->getAvailableAssets($this->product->id, $this->store->id, $from, $to)
            ->pluck('id')->all())->toContain($asset->id);
});

it('blocks only the returned serialized asset while other fleet assets remain available', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $returned = makeAsset($this->store, $this->product);
    $other = makeAsset($this->store, $this->product);
    $third = makeAsset($this->store, $this->product);
    $row = allocateOne($item, $returned);
    $from = Carbon::parse('2026-09-03T12:00:00Z');

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([
        'returned_at' => $from->toIso8601String(),
    ]));

    $available = app(AvailabilityService::class)
        ->getAvailableAssets($this->product->id, $this->store->id, $from, $from->copy()->addDay())
        ->pluck('id');

    expect($available)->not->toContain($returned->id)
        ->and($available)->toContain($other->id)
        ->and($available)->toContain($third->id);
});

it('finalises a damaged return without creating a repair', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $row = allocateOne($item, makeAsset($this->store, $this->product));

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([]));
    (new CheckAsset)($row->refresh(), CheckAssetData::from([
        'condition' => AssetCondition::Damaged->value,
        'damage_notes' => 'Surface damage recorded during inspection.',
    ]));

    expect($row->refresh()->status)->toBe(AssetAssignmentStatus::Finalised)
        ->and($row->condition_on_return)->toBe(AssetCondition::Damaged)
        ->and(Demand::query()->where('source_type', 'inspection_hold')->where('source_id', $row->id)->where('is_active', true)->exists())->toBeFalse();
});

it('hands a damaged return to a usable repair explicitly', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $stockLevel = makePhysicalAsset($this->store, $this->product);
    $row = allocateOne($item, $stockLevel);
    $returnStore = Store::factory()->create();

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from(['return_store_id' => $returnStore->id]));
    $repair = (new FinaliseAssetReturnInspection)($row->refresh(), CheckAssetData::from([
        'condition' => AssetCondition::Damaged->value,
        'damage_notes' => 'Bent encoder.',
    ]), true);

    expect($repair)->toBeInstanceOf(Repair::class)
        ->and($repair->asset_id)->toBe($stockLevel->asset_id)
        ->and($repair->store_id)->toBe($returnStore->id)
        ->and($repair->is_usable)->toBeTrue()
        ->and($repair->notes)->toBe('Bent encoder.')
        ->and($row->refresh()->status)->toBe(AssetAssignmentStatus::Finalised)
        ->and(Demand::query()->where('source_type', 'inspection_hold')->where('source_id', $row->id)->where('is_active', true)->exists())->toBeFalse()
        ->and(Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->where('is_active', true)->exists())->toBeFalse();
});

it('does not allow an inspection-only operator to create a repair handoff', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $stockLevel = makePhysicalAsset($this->store, $this->product);
    $row = allocateOne($item, $stockLevel);
    $operator = User::factory()->create();
    $operator->givePermissionTo(['opportunities.view', 'opportunities.edit']);

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([]));
    $this->actingAs($operator);

    expect(fn () => (new FinaliseAssetReturnInspection)($row->refresh(), CheckAssetData::from([
        'condition' => AssetCondition::Damaged->value,
        'damage_notes' => 'Needs maintenance review.',
    ]), true))->toThrow(AuthorizationException::class);

    expect($row->refresh()->status)->toBe(AssetAssignmentStatus::CheckedIn)
        ->and(Repair::query()->where('asset_id', $stockLevel->asset_id)->count())->toBe(0);
});

it('hands an unusable damaged return to one active repair demand without an availability gap', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $stockLevel = makePhysicalAsset($this->store, $this->product);
    $row = allocateOne($item, $stockLevel);
    $from = Carbon::now('UTC');

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from(['returned_at' => $from->toIso8601String()]));
    $repair = (new FinaliseAssetReturnInspection)($row->refresh(), CheckAssetData::from([
        'condition' => AssetCondition::Damaged->value,
        'damage_notes' => 'Broken connector.',
    ]), true, true);

    $repairDemand = Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole();

    expect($row->refresh()->status)->toBe(AssetAssignmentStatus::Finalised)
        ->and($repair->refresh()->is_usable)->toBeFalse()
        ->and($repairDemand->is_active)->toBeTrue()
        ->and($repairDemand->asset_id)->toBe($stockLevel->id)
        ->and(Demand::query()->where('source_type', 'inspection_hold')->where('source_id', $row->id)->where('is_active', true)->exists())->toBeFalse()
        ->and(app(AvailabilityService::class)->getAvailableAssets($this->product->id, $this->store->id, $from, $from->copy()->addDay())->pluck('id')->all())->not->toContain($stockLevel->id);
});

it('rolls back the inspection when a requested repair cannot be created', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $stockLevel = makeAsset($this->store, $this->product);
    $row = allocateOne($item, $stockLevel);

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([]));

    expect(fn () => (new FinaliseAssetReturnInspection)($row->refresh(), CheckAssetData::from([
        'condition' => AssetCondition::Damaged->value,
        'damage_notes' => 'No physical Asset record.',
    ]), true))->toThrow(ValidationException::class);

    expect($row->refresh()->status)->toBe(AssetAssignmentStatus::CheckedIn)
        ->and($row->condition_on_return)->toBeNull()
        ->and(Repair::query()->count())->toBe(0)
        ->and(Demand::query()->where('source_type', 'inspection_hold')->where('source_id', $row->id)->where('is_active', true)->exists())->toBeTrue();
});

it('does not create a second repair on a repeated handoff submission', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $stockLevel = makePhysicalAsset($this->store, $this->product);
    $row = allocateOne($item, $stockLevel);

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([]));
    $data = CheckAssetData::from([
        'condition' => AssetCondition::Damaged->value,
        'damage_notes' => 'Repeat submission.',
    ]);
    $repair = (new FinaliseAssetReturnInspection)($row->refresh(), $data, true, true);

    expect(fn () => (new FinaliseAssetReturnInspection)($row->refresh(), $data, true, true))
        ->toThrow(EventNotValid::class);

    expect(Repair::query()->where('asset_id', $stockLevel->asset_id)->count())->toBe(1)
        ->and(Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->where('is_active', true)->count())->toBe(1);
});

it('rejects dispatching on a quote (Order-only guard)', function () {
    // Build a Reserved quote with an allocated asset (allocation is allowed on a
    // reserved quote) but never convert to an order.
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Quote', 'store_id' => $this->store->id,
        'starts_at' => '2026-09-01T09:00:00Z', 'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new ConvertToQuotation)($opportunity);
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationReserved);

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $this->product->name, 'itemable_id' => $this->product->id, 'itemable_type' => Product::class, 'quantity' => '1',
    ]));
    $item = $opportunity->items()->firstOrFail();
    $asset = makeAsset($this->store, $this->product);
    $row = allocateOne($item, $asset);

    (new DispatchAsset)($row, DispatchAssetData::from([]));
})->throws(EventNotValid::class);

it('rejects returning an asset that was never dispatched', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $row = allocateOne($item, makeAsset($this->store, $this->product));

    (new ReturnAsset)($row, ReturnAssetData::from([]));
})->throws(EventNotValid::class);

it('auto-promotes the opportunity Active -> Dispatched -> On Hire across a partial then full dispatch', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '2');
    $a = makeAsset($this->store, $this->product);
    $b = makeAsset($this->store, $this->product);
    $rowA = allocateOne($item, $a);
    $rowB = allocateOne($item, $b);

    $opportunity = $item->opportunity()->firstOrFail();
    expect($opportunity->statusEnum())->toBe(OpportunityStatus::OrderActive);

    // Dispatch only A → some out, some not → Dispatched.
    (new DispatchAsset)($rowA, DispatchAssetData::from([]));
    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderDispatched);

    // Dispatch B → all out → On Hire.
    (new DispatchAsset)($rowB->refresh(), DispatchAssetData::from([]));
    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderOnHire);
});

it('auto-promotes to Returned then Checked as assets are returned and checked', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $row = allocateOne($item, makeAsset($this->store, $this->product));
    $opportunity = $item->opportunity()->firstOrFail();

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderOnHire);

    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([]));
    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderReturned);

    (new CheckAsset)($row->refresh(), CheckAssetData::from(['condition' => AssetCondition::Good->value]));
    expect($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderChecked);
});

it('fires exactly one OpportunityStatusPromoted per status change', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $row = allocateOne($item, makeAsset($this->store, $this->product));

    (new DispatchAsset)($row, DispatchAssetData::from([]));

    // One promotion event (Active → On Hire for a single-asset order) was persisted.
    $promotions = ActionLog::query()->where('action', 'opportunity.status_promoted')->count();
    expect($promotions)->toBe(1);
});

it('transitions the asset demand Committed -> Operational -> Closed', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $row = allocateOne($item, $asset = makeAsset($this->store, $this->product));

    $demand = fn () => Demand::query()->where('source_type', 'opportunity_item')->where('source_id', $item->id)->where('asset_id', $asset->id)->sole();

    expect($demand()->phase)->toBe(DemandPhase::Committed);

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    expect($demand()->phase)->toBe(DemandPhase::Operational);

    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([]));
    expect($demand()->phase)->toBe(DemandPhase::Closed)
        ->and($demand()->is_active)->toBeFalse();
});

it('pulls the demand start back when an asset is dispatched early', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $row = allocateOne($item, $asset = makeAsset($this->store, $this->product));

    // Planned start is 2026-09-01 09:00; dispatch a day early.
    (new DispatchAsset)($row, DispatchAssetData::from(['dispatched_at' => '2026-08-31T08:00:00Z']));

    $demand = Demand::query()->where('source_type', 'opportunity_item')->where('source_id', $item->id)->where('asset_id', $asset->id)->sole();
    expect($demand->starts_at->toIso8601String())->toBe('2026-08-31T08:00:00+00:00');
});

it('moves the demand end to the actual return time on a late return', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $row = allocateOne($item, $asset = makeAsset($this->store, $this->product));

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    // Planned end is 2026-09-05 17:00; return two days late.
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from(['returned_at' => '2026-09-07T10:00:00Z']));

    $demand = Demand::query()->where('source_type', 'opportunity_item')->where('source_id', $item->id)->where('asset_id', $asset->id)->sole();
    expect($demand->ends_at->toIso8601String())->toBe('2026-09-07T10:00:00+00:00');
});

it('releases quantity_allocated only when the asset is returned, not while it is out', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $row = allocateOne($item, $asset = makeAsset($this->store, $this->product));

    expect((float) $asset->refresh()->quantity_allocated)->toBe(1.0);

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    expect((float) $asset->refresh()->quantity_allocated)->toBe(1.0);

    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([]));
    expect((float) $asset->refresh()->quantity_allocated)->toBe(0.0);
});

it('reverts a returned asset back to on-hire, restoring allocation and demand', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $row = allocateOne($item, $asset = makeAsset($this->store, $this->product));
    $opportunity = $item->opportunity()->firstOrFail();

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([]));
    expect((float) $asset->refresh()->quantity_allocated)->toBe(0.0)
        ->and($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderReturned);

    // A mistaken check-in is reverted back to On Hire.
    (new RevertAssetStatus)($row->refresh(), RevertAssetStatusData::from([
        'revert_to' => AssetAssignmentStatus::OnHire->value,
        'reason' => 'scanned wrong asset',
    ]));

    expect($row->refresh()->status)->toBe(AssetAssignmentStatus::OnHire)
        ->and($row->returned_at)->toBeNull()
        // Allocation re-claimed (asset is out again).
        ->and((float) $asset->refresh()->quantity_allocated)->toBe(1.0)
        // Opportunity demoted back to On Hire.
        ->and($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderOnHire);

    // The asset demand is active again (Operational).
    $demand = Demand::query()->where('source_type', 'opportunity_item')->where('source_id', $item->id)->where('asset_id', $asset->id)->sole();
    expect($demand->phase)->toBe(DemandPhase::Operational)
        ->and($demand->is_active)->toBeTrue();
});

it('rejects reverting forward (to a later status)', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $row = allocateOne($item, makeAsset($this->store, $this->product));
    (new DispatchAsset)($row, DispatchAssetData::from([]));

    (new RevertAssetStatus)($row->refresh(), RevertAssetStatusData::from([
        'revert_to' => AssetAssignmentStatus::OnHire->value,
    ]));
})->throws(EventNotValid::class);

it('books out and checks in several assets atomically via the batch wrappers', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '2');
    $a = makeAsset($this->store, $this->product);
    $b = makeAsset($this->store, $this->product);
    $rowA = allocateOne($item, $a);
    $rowB = allocateOne($item, $b);
    $opportunity = $item->opportunity()->firstOrFail();

    (new QuickBookOut)($opportunity, QuickBookOutData::from([
        'asset_ids' => [$rowA->id, $rowB->id],
    ]));

    expect($rowA->refresh()->status)->toBe(AssetAssignmentStatus::Dispatched)
        ->and($rowB->refresh()->status)->toBe(AssetAssignmentStatus::Dispatched)
        // All out in one batch → On Hire, promoted once.
        ->and($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderOnHire);

    (new QuickCheckIn)($opportunity->refresh(), QuickCheckInData::from([
        'asset_ids' => [$rowA->id, $rowB->id],
        'finalise' => true,
    ]));

    expect($rowA->refresh()->status)->toBe(AssetAssignmentStatus::Finalised)
        ->and($rowB->refresh()->status)->toBe(AssetAssignmentStatus::Finalised)
        ->and($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderChecked);
});

it('fires the batch promotion once for quick_book_out', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '2');
    $rowA = allocateOne($item, makeAsset($this->store, $this->product));
    $rowB = allocateOne($item, makeAsset($this->store, $this->product));
    $opportunity = $item->opportunity()->firstOrFail();

    (new QuickBookOut)($opportunity, QuickBookOutData::from(['asset_ids' => [$rowA->id, $rowB->id]]));

    // Exactly one promotion (Active → On Hire) despite two assets dispatched.
    $promotions = ActionLog::query()->where('action', 'opportunity.status_promoted')->count();
    expect($promotions)->toBe(1);
});

it('rolls back the whole quick_book_out batch when one asset is invalid', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '2');
    $rowA = allocateOne($item, makeAsset($this->store, $this->product));
    $rowB = allocateOne($item, makeAsset($this->store, $this->product));
    // Dispatch B already so the second fire in the batch fails (wrong status).
    (new DispatchAsset)($rowB, DispatchAssetData::from([]));
    $opportunity = $item->opportunity()->firstOrFail();

    expect(fn () => (new QuickBookOut)($opportunity->refresh(), QuickBookOutData::from([
        'asset_ids' => [$rowA->id, $rowB->id],
    ])))->toThrow(EventNotValid::class);

    // A is NOT dispatched — the batch rolled back atomically.
    expect($rowA->refresh()->status)->toBe(AssetAssignmentStatus::Allocated);
});

describe('bulk quantity fulfilment', function () {
    beforeEach(function () {
        $this->bulkProduct = Product::factory()->rental()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $this->bulkProduct->id,
            'store_id' => $this->store->id,
            'quantity_held' => 100,
        ]);
    });

    it('tracks partial bulk dispatch and return with effective_quantity', function () {
        $item = makeFulfilmentOrder($this->store, $this->bulkProduct, '100');
        $opportunity = $item->opportunity()->firstOrFail();

        // Dispatch 60 of 100.
        (new DispatchBulkQuantity)($item, BulkDispatchData::from(['quantity' => '60']));
        expect((float) $item->refresh()->dispatched_quantity)->toBe(60.0)
            ->and($opportunity->refresh()->statusEnum())->toBe(OpportunityStatus::OrderOnHire);

        // Return 40 → effective demand = 100 - 40 = 60.
        (new ReturnBulkQuantity)($item->refresh(), BulkReturnData::from(['quantity' => '40']));
        expect((float) $item->refresh()->returned_quantity)->toBe(40.0);

        $demand = Demand::query()->where('source_type', 'opportunity_item')->where('source_id', $item->id)->whereNull('asset_id')->sole();
        expect((int) $demand->quantity)->toBe(60);
    });

    it('rejects dispatching more than the requested bulk quantity', function () {
        $item = makeFulfilmentOrder($this->store, $this->bulkProduct, '50');
        (new DispatchBulkQuantity)($item, BulkDispatchData::from(['quantity' => '60']));
    })->throws(EventNotValid::class);

    it('rejects returning more than is out on hire', function () {
        $item = makeFulfilmentOrder($this->store, $this->bulkProduct, '50');
        (new DispatchBulkQuantity)($item, BulkDispatchData::from(['quantity' => '30']));
        (new ReturnBulkQuantity)($item->refresh(), BulkReturnData::from(['quantity' => '40']));
    })->throws(EventNotValid::class);

    it('adjusts a bulk line quantity but not below what is dispatched', function () {
        $item = makeFulfilmentOrder($this->store, $this->bulkProduct, '50');
        (new DispatchBulkQuantity)($item, BulkDispatchData::from(['quantity' => '30']));

        (new AdjustBulkQuantity)($item->refresh(), BulkAdjustData::from(['new_quantity' => '40', 'reason' => 'less needed']));
        expect((float) $item->refresh()->quantity)->toBe(40.0);

        // Cannot drop below the 30 already dispatched.
        expect(fn () => (new AdjustBulkQuantity)($item->refresh(), BulkAdjustData::from(['new_quantity' => '20'])))
            ->toThrow(EventNotValid::class);
    });

    it('blocks period and Store changes after bulk dispatch history exists', function () {
        $item = makeFulfilmentOrder($this->store, $this->bulkProduct, '50');
        $opportunity = $item->opportunity()->firstOrFail();
        $otherStore = Store::factory()->create();

        (new DispatchBulkQuantity)($item, BulkDispatchData::from(['quantity' => '30']));

        expect(fn () => (new UpdateOpportunity)(
            $opportunity->refresh(),
            UpdateOpportunityData::from(['planning_starts_at' => '2026-08-31T08:00:00Z']),
        ))->toThrow(ValidationException::class);

        expect(fn () => (new UpdateOpportunity)(
            $opportunity->refresh(),
            UpdateOpportunityData::from(['store_id' => $otherStore->id]),
        ))->toThrow(ValidationException::class);

        (new ReturnBulkQuantity)($item->refresh(), BulkReturnData::from(['quantity' => '30']));

        expect(fn () => (new UpdateOpportunity)(
            $opportunity->refresh(),
            UpdateOpportunityData::from(['planning_starts_at' => '2026-09-01T08:00:00Z']),
        ))->toThrow(ValidationException::class);
    });
});

it('rebuilds asset statuses, demands and opportunity status identically on replay without re-firing the promotion', function () {
    $item = makeFulfilmentOrder($this->store, $this->product, '1');
    $row = allocateOne($item, $asset = makeAsset($this->store, $this->product));
    $opportunity = $item->opportunity()->firstOrFail();

    (new DispatchAsset)($row, DispatchAssetData::from([]));
    (new ReturnAsset)($row->refresh(), ReturnAssetData::from([]));

    $statusBefore = $opportunity->refresh()->statusEnum();
    $assetStatusBefore = $row->refresh()->status;
    $promotionsBefore = ActionLog::query()->where('action', 'opportunity.status_promoted')->count();
    $demandPhaseBefore = Demand::query()->where('source_type', 'opportunity_item')->where('source_id', $item->id)->where('asset_id', $asset->id)->sole()->phase;

    // Wipe projections + allocation, then replay the entire stream.
    OpportunityItemAsset::query()->delete();
    StockLevel::query()->update(['quantity_allocated' => 0]);

    Verbs::replay();

    $rowAfter = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->sole();

    expect($rowAfter->status)->toBe($assetStatusBefore)
        ->and($opportunity->refresh()->statusEnum())->toBe($statusBefore)
        // The promotion event replayed through its own apply/handle — it was NOT
        // re-fired by the asset events' fired() hook (which never runs on replay),
        // so the promotion count is unchanged.
        ->and(ActionLog::query()->where('action', 'opportunity.status_promoted')->count())->toBe($promotionsBefore);

    // Demands are not rebuilt by replay (unlessReplaying) — the last synced phase persists.
    $demandPhaseAfter = Demand::query()->where('source_type', 'opportunity_item')->where('source_id', $item->id)->where('asset_id', $asset->id)->sole()->phase;
    expect($demandPhaseAfter)->toBe($demandPhaseBefore);
});
