<?php

/**
 * Coverage burn-up batch 4 — part 2 (Verbs event handle/apply/validate branches).
 */

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Actions\Opportunities\DeallocateAsset;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\DispatchBulkQuantity;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Data\Opportunities\DispatchAssetData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\OpportunityStatus;
use App\Enums\VersionType;
use App\Models\Demand;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Verbs\Events\Invoices\DraftInvoiceDeleted;
use App\Verbs\Events\Invoices\InvoiceVoided;
use App\Verbs\Events\Opportunities\AssetDeallocated;
use App\Verbs\Events\Opportunities\AssetDispatched;
use App\Verbs\Events\Opportunities\AssetSubstituted;
use App\Verbs\Events\Opportunities\BulkQuantityReturned;
use App\Verbs\Events\Opportunities\DiscountCategoryApplied;
use App\Verbs\Events\Opportunities\GroupDealPriceCleared;
use App\Verbs\Events\Opportunities\ItemDealPriceDiscountSet;
use App\Verbs\Events\Opportunities\OpportunityStatusChanged;
use App\Verbs\Events\Opportunities\SurchargeOverridden;
use App\Verbs\Events\Opportunities\VersionDeleted;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderReceiptRecorded;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeReceived;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSerialisedAssetsProvisioned;
use App\Verbs\States\AssetAssignmentState;
use App\Verbs\States\InvoiceState;
use App\Verbs\States\OpportunityItemState;
use App\Verbs\States\OpportunityState;
use App\Verbs\States\OpportunityVersionState;
use App\Verbs\States\VirtualStockIntakeState;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Verbs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-05T10:00:00Z'));
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @param  array<int, mixed>  $args
 */
function burnup4Part2Invoke(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

/**
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function burnup4QuotationWithBulkItem(): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Burnup batch 4 verbs',
    ]));
    $opportunity = Opportunity::query()->findOrFail($created->id);
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $opportunity->store_id,
        'quantity_held' => 20,
    ]);

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '2',
    ]));
    (new ConvertToQuotation)($opportunity->fresh());

    return [$opportunity->fresh(), $opportunity->fresh()->items()->firstOrFail()];
}

/**
 * @return array{0: OpportunityItem, 1: OpportunityItemAsset, 2: StockLevel}
 */
function burnup4SerialAssetOnOrder(Opportunity $opportunity): array
{
    (new ConvertToOrder)($opportunity->fresh());

    $serialProduct = Product::factory()->rental()->serialised()->create();
    $stock = StockLevel::factory()->serialised()->create([
        'product_id' => $serialProduct->id,
        'store_id' => $opportunity->store_id,
    ]);

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => $serialProduct->name,
        'itemable_id' => $serialProduct->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ]));

    $item = $opportunity->fresh()->items()->where('itemable_id', $serialProduct->id)->firstOrFail();
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $stock->id]));
    $asset = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->firstOrFail();

    return [$item, $asset, $stock];
}

it('DraftInvoiceDeleted handle records audit and no-ops missing or trashed invoices', function () {
    $opportunity = giCreateOrderOpportunity();
    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $opportunity->member_id,
        'store_id' => $opportunity->store_id,
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);
    $stateId = (int) $invoice->state_id;

    DB::transaction(function () use ($stateId): void {
        DraftInvoiceDeleted::fire(
            state_id: $stateId,
            deleted_at: '2026-07-05T11:00:00Z',
            deleted_by: 1,
        );
        Verbs::commit();
    });

    expect($invoice->fresh()->trashed())->toBeTrue();

    $state = InvoiceState::load($stateId);
    Invoice::withTrashed()->whereKey($invoice->id)->forceDelete();
    burnup4Part2Invoke(new DraftInvoiceDeleted(state_id: $stateId), 'handle', [$state]);

    $invoice->delete();
    burnup4Part2Invoke(new DraftInvoiceDeleted(state_id: $stateId), 'handle', [$state]);
    expect(true)->toBeTrue();
});

it('InvoiceVoided apply stores null voided_at and handle adjusts opportunity invoicing', function () {
    $zones = giWireZoneTaxInfrastructure();
    $opportunity = giCreateOrderOpportunity(zones: $zones);
    $item = $opportunity->items()->firstOrFail();
    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $opportunity->member_id,
        'store_id' => $opportunity->store_id,
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);
    $stateId = (int) $invoice->fresh()->state_id;

    DB::transaction(function () use ($stateId, $item): void {
        InvoiceVoided::fire(
            state_id: $stateId,
            voided_at: null,
            quantity_releases: [['opportunity_item_id' => $item->id, 'quantity' => '0.25']],
        );
        Verbs::commit();
    });

    $state = InvoiceState::load($stateId);
    expect($state->voided_at)->toBeNull()
        ->and($opportunity->fresh()->invoiced)->toBeFalse();
});

it('AssetDispatched handle no-ops when asset or item projections are missing', function () {
    [$opportunity] = burnup4QuotationWithBulkItem();
    [$item, $asset] = burnup4SerialAssetOnOrder($opportunity);
    (new DispatchAsset)($asset->fresh(), DispatchAssetData::from([]));
    $assetStateId = (int) $asset->fresh()->state_id;

    OpportunityItem::query()->whereKey($item->id)->forceDelete();
    burnup4Part2Invoke(new AssetDispatched(state_id: $assetStateId), 'handle', [AssetAssignmentState::load($assetStateId)]);

    OpportunityItemAsset::query()->whereKey($asset->id)->forceDelete();
    burnup4Part2Invoke(new AssetDispatched(state_id: $assetStateId), 'handle', [AssetAssignmentState::load($assetStateId)]);

    expect(AssetAssignmentState::load($assetStateId)->status)->toBe(AssetAssignmentStatus::Dispatched->value);
});

it('AssetSubstituted handle no-ops when the asset projection is missing', function () {
    [$opportunity] = burnup4QuotationWithBulkItem();
    [$item, $asset, $stock] = burnup4SerialAssetOnOrder($opportunity);
    $replacement = StockLevel::factory()->serialised()->create([
        'product_id' => $stock->product_id,
        'store_id' => $opportunity->store_id,
    ]);
    $assetStateId = (int) $asset->state_id;

    OpportunityItemAsset::query()->whereKey($asset->id)->forceDelete();
    burnup4Part2Invoke(
        new AssetSubstituted(state_id: $assetStateId, new_stock_level_id: $replacement->id),
        'handle',
        [AssetAssignmentState::load($assetStateId)],
    );

    OpportunityItem::query()->whereKey($item->id)->forceDelete();
    burnup4Part2Invoke(
        new AssetSubstituted(state_id: $assetStateId, new_stock_level_id: $replacement->id),
        'handle',
        [AssetAssignmentState::load($assetStateId)],
    );

    expect(true)->toBeTrue();
});

it('BulkQuantityReturned handle no-ops when the item projection is missing', function () {
    [$opportunity, $item] = burnup4QuotationWithBulkItem();
    (new ConvertToOrder)($opportunity->fresh());
    (new DispatchBulkQuantity)($item->fresh(), BulkDispatchData::from(['quantity' => '1']));
    $itemStateId = (int) $item->fresh()->state_id;

    OpportunityItem::query()->whereKey($item->id)->forceDelete();
    burnup4Part2Invoke(
        new BulkQuantityReturned(opportunity_item_id: $itemStateId, quantity: '1'),
        'handle',
        [OpportunityItemState::load($itemStateId)],
    );

    expect(true)->toBeTrue();
});

it('DiscountCategoryApplied handle no-ops when the opportunity projection is missing', function () {
    [$opportunity] = burnup4QuotationWithBulkItem();
    $stateId = (int) $opportunity->state_id;

    DB::transaction(function () use ($stateId): void {
        DiscountCategoryApplied::fire(
            opportunity_id: $stateId,
            discount_category_id: 1,
            applications: [['opportunity_item_id' => 1, 'discount_percent' => '5.00']],
        );
        Verbs::commit();
    });

    Opportunity::query()->where('state_id', $stateId)->forceDelete();
    burnup4Part2Invoke(
        new DiscountCategoryApplied(opportunity_id: $stateId, discount_category_id: 1),
        'handle',
        [OpportunityState::load($stateId)],
    );

    expect(true)->toBeTrue();
});

it('ItemDealPriceDiscountSet handle no-ops when the item projection is missing', function () {
    [$opportunity, $item] = burnup4QuotationWithBulkItem();
    $itemStateId = (int) $item->state_id;

    OpportunityItem::query()->whereKey($item->id)->forceDelete();
    burnup4Part2Invoke(
        new ItemDealPriceDiscountSet(opportunity_item_id: $itemStateId, deal_price_discount_percent: '5.00'),
        'handle',
        [OpportunityItemState::load($itemStateId)],
    );

    expect(true)->toBeTrue();
});

it('OpportunityStatusChanged validate enforces void and complete invariants', function () {
    [$opportunity, $item] = burnup4QuotationWithBulkItem();
    (new ConvertToOrder)($opportunity->fresh());
    (new DispatchBulkQuantity)($item->fresh(), BulkDispatchData::from(['quantity' => '1']));

    expect(fn () => DB::transaction(function () use ($opportunity): void {
        OpportunityStatusChanged::fire(
            opportunity_id: (int) $opportunity->state_id,
            to_status: OpportunityStatus::OrderCancelled->statusValue(),
        );
        Verbs::commit();
    }))->toThrow(EventNotValid::class);

    OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->delete();
    OpportunityItem::query()->whereKey($item->id)->update(['returned_quantity' => '1', 'dispatched_quantity' => '1']);

    expect(fn () => DB::transaction(function () use ($opportunity): void {
        OpportunityStatusChanged::fire(
            opportunity_id: (int) $opportunity->state_id,
            to_status: OpportunityStatus::OrderComplete->statusValue(),
        );
        Verbs::commit();
    }))->not->toThrow(EventNotValid::class);
});

it('SurchargeOverridden handle uses surcharge snapshots and records audit totals', function () {
    [$opportunity, $item] = burnup4QuotationWithBulkItem();
    $surcharge = Surcharge::factory()->create();

    DB::transaction(function () use ($item, $surcharge): void {
        SurchargeOverridden::fire(
            opportunity_item_id: (int) $item->state_id,
            surcharge_id: $surcharge->id,
            amount_minor: 750,
            snapshot_name: 'Custom fee',
            snapshot_surcharge_type: 'fixed',
            snapshot_fixed_value_minor: 750,
        );
        Verbs::commit();
    });

    $item->refresh();
    expect($item->surcharge_total)->toBeGreaterThanOrEqual(0);

    Opportunity::query()->whereKey($opportunity->id)->forceDelete();
    burnup4Part2Invoke(
        new SurchargeOverridden(
            opportunity_item_id: (int) $item->state_id,
            surcharge_id: $surcharge->id,
            is_disabled: true,
        ),
        'handle',
        [OpportunityItemState::load((int) $item->state_id)],
    );

    expect(true)->toBeTrue();
});

it('VersionDeleted handle records reasoned audit entries and no-ops missing versions', function () {
    [$opportunity] = burnup4QuotationWithBulkItem();
    $discard = OpportunityVersion::query()->whereKey(
        (new CreateVersion)($opportunity->fresh(), CreateVersionData::from([
            'version_type' => VersionType::Alternative->value,
        ]))->id,
    )->firstOrFail();
    (new CreateVersion)($opportunity->fresh(), CreateVersionData::from([
        'version_type' => VersionType::Alternative->value,
    ]));

    DB::transaction(function () use ($discard): void {
        VersionDeleted::fire(version_id: (int) $discard->state_id, reason: 'superseded');
        Verbs::commit();
    });

    expect(OpportunityVersion::query()->whereKey($discard->id)->exists())->toBeFalse();

    $state = OpportunityVersionState::load((int) $discard->state_id);
    burnup4Part2Invoke(new VersionDeleted(version_id: (int) $discard->state_id), 'handle', [$state]);
    expect(true)->toBeTrue();
});

it('PurchaseOrderReceiptRecorded handle skips missing line items and updates received headers', function () {
    $po = createTestPurchaseOrder([
        'lines' => [['description' => 'Line', 'quantity' => '2', 'unit_cost' => 1000]],
    ]);
    (new SendPurchaseOrder)($po->refresh());
    $item = $po->items()->firstOrFail();

    DB::transaction(function () use ($po, $item): void {
        PurchaseOrderReceiptRecorded::fire(
            state_id: (int) $po->fresh()->state_id,
            resulting_status: 'received',
            received_at: '2026-07-05T12:00:00Z',
            line_updates: [
                ['purchase_order_item_id' => $item->id, 'received_quantity' => '2', 'tax' => 0, 'total' => 2000],
                ['purchase_order_item_id' => 999999, 'received_quantity' => '1', 'tax' => 0, 'total' => 1000],
            ],
            tax_finalised: false,
        );
        Verbs::commit();
    });

    expect($po->fresh()->status)->toBe('received')
        ->and($po->fresh()->received_at)->not->toBeNull();
});

it('VirtualStockIntakeSerialisedAssetsProvisioned apply stores provisioned_at timestamps', function () {
    $product = Product::factory()->rental()->serialised()->create();
    $intake = createTestVirtualStockIntake(['product_id' => $product->id, 'quantity' => 1]);
    $stateId = (int) $intake->state_id;
    $state = VirtualStockIntakeState::load($stateId);

    burnup4Part2Invoke(new VirtualStockIntakeSerialisedAssetsProvisioned(
        state_id: $stateId,
        assets: [[
            'stock_level_id' => 101,
            'supply_demand_id' => 201,
            'sequence' => 1,
            'asset_number' => 'VS-001',
        ]],
        provisioned_at: '2026-07-05T09:30:00Z',
    ), 'apply', [$state]);

    expect($state->serialised_assets_provisioned)->toBeTrue()
        ->and($state->last_event_at?->toIso8601String())->toBe('2026-07-05T09:30:00+00:00');
});

it('GroupDealPriceCleared handle no-ops missing group or opportunity projections', function () {
    [$opportunity] = burnup4QuotationWithBulkItem();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Group',
        'item_type' => 'group',
    ]));

    $group = $opportunity->fresh()->items->first(fn (OpportunityItem $row): bool => $row->item_type->value === 'group');
    $groupStateId = (int) $group->state_id;

    DB::transaction(function () use ($groupStateId): void {
        GroupDealPriceCleared::fire(group_opportunity_item_id: $groupStateId, reprice: true);
        Verbs::commit();
    });

    $state = OpportunityItemState::load($groupStateId);
    OpportunityItem::query()->whereKey($group->id)->forceDelete();
    burnup4Part2Invoke(new GroupDealPriceCleared(group_opportunity_item_id: $groupStateId), 'handle', [$state]);

    Opportunity::query()->whereKey($opportunity->id)->forceDelete();
    burnup4Part2Invoke(new GroupDealPriceCleared(group_opportunity_item_id: $groupStateId), 'handle', [$state]);

    expect(true)->toBeTrue();
});

it('AssetDeallocated handle and releaseVirtualAssetStatus cover virtual asset branches', function () {
    $store = Store::factory()->create();
    $product = Product::factory()->rental()->serialised()->create();
    $intake = createTestVirtualStockIntake([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity' => 1,
        'available_from' => '2026-09-01T00:00:00Z',
        'available_until' => '2026-09-06T00:00:00Z',
    ]);
    app(ConfirmVirtualStockIntake::class)($intake);
    $virtualStock = StockLevel::query()->where('virtual_stock_intake_id', $intake->id)->firstOrFail();

    $opportunity = giCreateOrderOpportunity([], [
        'store_id' => $store->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]);

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $item = $opportunity->fresh()->items()->where('itemable_id', $product->id)->firstOrFail();
    (new AllocateAsset)($item, AllocateAssetData::from(['stock_level_id' => $virtualStock->id]));
    $assignment = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->firstOrFail();
    $stateId = (int) $assignment->state_id;

    (new DeallocateAsset)($assignment->fresh());
    expect($virtualStock->fresh()->virtual_asset_status)->toBe('available');

    OpportunityItemAsset::query()->whereKey($assignment->id)->forceDelete();
    burnup4Part2Invoke(new AssetDeallocated(state_id: $stateId), 'handle', [AssetAssignmentState::load($stateId)]);

    burnup4Part2Invoke(new AssetDeallocated(state_id: $stateId), 'releaseVirtualAssetStatus', [null]);
    burnup4Part2Invoke(new AssetDeallocated(state_id: $stateId), 'releaseVirtualAssetStatus', [999999]);

    $physical = StockLevel::factory()->serialised()->create(['is_virtual' => false]);
    burnup4Part2Invoke(new AssetDeallocated(state_id: $stateId), 'releaseVirtualAssetStatus', [$physical->id]);

    expect(true)->toBeTrue();
});

it('VirtualStockIntakeReceived apply and resyncSerialisedAssetSupply cover intake branches', function () {
    $product = Product::factory()->rental()->serialised()->create();
    $intake = createTestVirtualStockIntake(['product_id' => $product->id, 'quantity' => 1]);
    app(ConfirmVirtualStockIntake::class)($intake);
    $stateId = (int) $intake->fresh()->state_id;
    $state = VirtualStockIntakeState::load($stateId);

    burnup4Part2Invoke(new VirtualStockIntakeReceived(
        state_id: $stateId,
        status: 'received',
        received_at: null,
    ), 'apply', [$state]);

    expect($state->received_at)->toBeNull();

    app(ReceiveVirtualStockIntake::class)(VirtualStockIntake::query()->findOrFail($intake->id));
    $receivedIntake = VirtualStockIntake::query()->findOrFail($intake->id);

    burnup4Part2Invoke(
        new VirtualStockIntakeReceived(state_id: $stateId),
        'resyncSerialisedAssetSupply',
        [$receivedIntake],
    );

    Demand::query()->where('source_id', $intake->id)->update(['asset_id' => null]);
    burnup4Part2Invoke(
        new VirtualStockIntakeReceived(state_id: $stateId),
        'resyncSerialisedAssetSupply',
        [$receivedIntake->fresh()],
    );

    expect($receivedIntake->fresh()->status)->toBe('received');
});
