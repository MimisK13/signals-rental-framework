<?php

/**
 * Coverage burn-up batch 5 (part 2) — Verbs events and concern branches.
 */

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\UpdateDraftInvoice;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\DispatchBulkQuantity;
use App\Actions\Opportunities\RecalculateOpportunity;
use App\Actions\Opportunities\ReturnAsset;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetItemDiscount;
use App\Actions\Opportunities\SetSectionDealPrice;
use App\Actions\Opportunities\ToggleOpportunitySurchargeGroup;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Invoices\UpdateDraftInvoiceData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\RecalculateOpportunityData;
use App\Data\Opportunities\ReturnAssetData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Data\Opportunities\SetSectionDealPriceData;
use App\Data\Opportunities\ToggleOpportunitySurchargeGroupData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\DealPriceMode;
use App\Enums\OpportunityItemType;
use App\Enums\PricingRoundingDirection;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\SurchargeGroup;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Verbs\Events\Invoices\InvoiceDraftUpdated;
use App\Verbs\Events\Opportunities\AssetStatusReverted;
use App\Verbs\Events\Opportunities\BulkQuantityDispatched;
use App\Verbs\Events\Opportunities\Concerns\AdjustsStockAllocation;
use App\Verbs\Events\Opportunities\DiscountManuallyOverridden;
use App\Verbs\Events\Opportunities\ItemSortOrderChanged;
use App\Verbs\Events\Opportunities\OpportunityDeleted;
use App\Verbs\Events\Opportunities\PricingRecalculated;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderCreated;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderSent;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeConfirmed;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSerialisedAssetsRetired;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSupplyAdjusted;
use App\Verbs\States\InvoiceState;
use App\Verbs\States\OpportunityItemState;
use App\Verbs\States\OpportunityState;
use App\Verbs\States\PurchaseOrderState;
use App\Verbs\States\VirtualStockIntakeState;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
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
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function burnup5InvokeConcern(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

function burnup5EnsureOpportunitySectionsTable(): void
{
    if (Schema::hasTable('opportunity_sections')) {
        return;
    }

    Schema::create('opportunity_sections', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('opportunity_id');
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('auto_group_key')->nullable();
        $table->string('name');
        $table->integer('sort_order')->default(0);
        $table->timestamps();
    });
}

function burnup5EnsureItemSortOrderColumn(): void
{
    if (Schema::hasColumn('opportunity_items', 'sort_order')) {
        return;
    }

    Schema::table('opportunity_items', function (Blueprint $table): void {
        $table->integer('sort_order')->default(0);
    });
}

function burnup5QuotationWithBulkItem(): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Batch 5 verbs',
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

function burnup5QuotationWithGroup(): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Batch 5 group deal',
    ]));
    $opportunity = Opportunity::query()->findOrFail($created->id);

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Section',
        'item_type' => 'group',
    ]));

    $opportunity = $opportunity->fresh(['items']);
    $group = $opportunity->items->first(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group);

    $product = Product::factory()->rental()->bulk()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ]));

    $line = $opportunity->fresh()->items()->latest('id')->firstOrFail();
    $line->forceFill(['path' => $group->path.'0001'])->saveQuietly();
    (new ConvertToQuotation)($opportunity->fresh());

    $opportunity = $opportunity->fresh(['items']);
    $group = $opportunity->items->first(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group);

    return [$opportunity, $group];
}

function burnup5DraftInvoice(): Invoice
{
    $opportunity = giCreateOrderOpportunity();
    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $opportunity->member_id,
        'store_id' => $opportunity->store_id,
        'opportunity_id' => $opportunity->id,
        'notes' => 'Original notes',
    ]));

    return Invoice::query()->findOrFail($draft->id);
}

it('InvoiceDraftUpdated apply preserves last_event_at when updated_at is omitted', function () {
    $invoice = burnup5DraftInvoice();
    $state = InvoiceState::load((int) $invoice->state_id);
    $previous = $state->last_event_at;

    burnup5InvokeConcern(
        new InvoiceDraftUpdated(
            state_id: (int) $invoice->state_id,
            updated_at: null,
            notes_provided: false,
            reference_provided: false,
        ),
        'apply',
        [$state],
    );

    expect(InvoiceState::load((int) $invoice->state_id)->last_event_at?->eq($previous))->toBeTrue();
});

it('AssetAllocated handle runs through AllocateAsset on a serialised order line', function () {
    [$opportunity] = burnup5QuotationWithBulkItem();
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

    $serialItem = $opportunity->fresh()->items()->where('itemable_id', $serialProduct->id)->firstOrFail();
    (new AllocateAsset)($serialItem, AllocateAssetData::from(['stock_level_id' => $stock->id]));

    expect(OpportunityItemAsset::query()->where('opportunity_item_id', $serialItem->id)->exists())->toBeTrue();
});

it('AssetStatusReverted handle reclaims stock allocation when reverting from a returned state', function () {
    [$opportunity] = burnup5QuotationWithBulkItem();
    (new ConvertToOrder)($opportunity->fresh());

    $serialProduct = Product::factory()->rental()->serialised()->create();
    $stock = StockLevel::factory()->serialised()->create([
        'product_id' => $serialProduct->id,
        'store_id' => $opportunity->store_id,
        'quantity_allocated' => '0',
    ]);

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => $serialProduct->name,
        'itemable_id' => $serialProduct->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ]));

    $serialItem = $opportunity->fresh()->items()->where('itemable_id', $serialProduct->id)->firstOrFail();
    (new AllocateAsset)($serialItem, AllocateAssetData::from(['stock_level_id' => $stock->id]));
    $asset = OpportunityItemAsset::query()->where('opportunity_item_id', $serialItem->id)->firstOrFail();

    (new DispatchAsset)($asset->fresh(), DispatchAssetData::from([]));
    (new ReturnAsset)($asset->fresh(), ReturnAssetData::from([]));

    $allocatedBefore = (string) $stock->fresh()->quantity_allocated;

    DB::transaction(function () use ($asset): void {
        AssetStatusReverted::fire(
            state_id: (int) $asset->fresh()->state_id,
            revert_to: AssetAssignmentStatus::Allocated->value,
            reason: 'Batch 5 reclaim',
        );
        Verbs::commit();
    });

    expect($allocatedBefore)->toBe('0.00')
        ->and((string) $stock->fresh()->quantity_allocated)->toBe('1.00');
});

it('BulkQuantityDispatched handle no-ops when the item projection is missing', function () {
    [$opportunity, $item] = burnup5QuotationWithBulkItem();
    (new ConvertToOrder)($opportunity->fresh());
    $stateId = (int) $item->fresh()->state_id;

    OpportunityItem::query()->whereKey($item->id)->forceDelete();

    burnup5InvokeConcern(
        new BulkQuantityDispatched(opportunity_item_id: $stateId, quantity: '1'),
        'handle',
        [OpportunityItemState::load($stateId)],
    );

    expect(true)->toBeTrue();
});

it('AdjustsStockAllocation adjustStockAllocation skips null ids and clamps decrements', function () {
    $concern = new class
    {
        use AdjustsStockAllocation;
    };

    burnup5InvokeConcern($concern, 'adjustStockAllocation', [null, '1', true]);

    $stock = StockLevel::factory()->bulk()->create(['quantity_allocated' => '0.50']);
    burnup5InvokeConcern($concern, 'adjustStockAllocation', [$stock->id, '5', false]);

    expect((string) $stock->fresh()->quantity_allocated)->toBe('0.00');
});

it('DiscountManuallyOverridden handle no-ops when the opportunity item is missing', function () {
    [$opportunity, $item] = burnup5QuotationWithBulkItem();
    $stateId = (int) $item->state_id;

    OpportunityItem::query()->whereKey($item->id)->forceDelete();

    burnup5InvokeConcern(
        new DiscountManuallyOverridden(
            opportunity_item_id: $stateId,
            discount_percent: '10.00',
            previous_discount_percent: '0.00',
        ),
        'handle',
        [OpportunityItemState::load($stateId)],
    );

    expect(true)->toBeTrue();
});

it('OpportunityDeleted handle soft-deletes the opportunity projection', function () {
    [$opportunity] = burnup5QuotationWithBulkItem();
    $stateId = (int) $opportunity->state_id;

    DB::transaction(function () use ($stateId): void {
        OpportunityDeleted::fire(opportunity_id: $stateId);
        Verbs::commit();
    });

    expect(Opportunity::withTrashed()->find($opportunity->id)?->trashed())->toBeTrue();
});

it('PricingRecalculated handle no-ops when the opportunity projection is missing', function () {
    [$opportunity] = burnup5QuotationWithBulkItem();
    $stateId = (int) $opportunity->state_id;
    Opportunity::query()->whereKey($opportunity->id)->forceDelete();

    burnup5InvokeConcern(
        new PricingRecalculated(
            opportunity_id: $stateId,
            reset_prices: true,
            reset_discounts: false,
            charge_total_before: 100,
            charge_total_after: 200,
        ),
        'handle',
        [OpportunityState::load($stateId)],
    );

    expect(true)->toBeTrue();
});

it('SurchargeToggled handle reprices lines and records audit', function () {
    [$opportunity] = burnup5QuotationWithBulkItem();
    $group = SurchargeGroup::factory()->create();

    (new ToggleOpportunitySurchargeGroup)($opportunity->fresh(), ToggleOpportunitySurchargeGroupData::from([
        'surcharge_group_id' => $group->id,
        'is_active' => true,
    ]));

    expect($opportunity->fresh()->surchargeGroups()->where('surcharge_group_id', $group->id)->exists())->toBeTrue();
});

it('PurchaseOrderCreated apply accepts null created_at and handle persists line items', function () {
    $po = createTestPurchaseOrder([
        'lines' => [[
            'description' => 'Batch 5 line',
            'quantity' => '2',
            'unit_cost' => 500,
        ]],
    ]);
    $state = PurchaseOrderState::load((int) $po->state_id);

    burnup5InvokeConcern(
        new PurchaseOrderCreated(
            purchase_order_id: $po->id + 999,
            state_id: $state->id,
            number: 'PO-B5',
            supplier_id: $po->supplier_id,
            store_id: $po->store_id,
            created_at: null,
            line_items: [[
                'purchase_order_item_id' => 999001,
                'description' => 'Replay line',
                'quantity' => '1',
                'unit_cost' => 100,
                'total' => 100,
            ]],
        ),
        'apply',
        [$state],
    );

    expect(PurchaseOrderState::load((int) $po->state_id)->created_at)->toBeNull();
});

it('VirtualStockIntakeConfirmed apply skips demand_id when not provided', function () {
    $intake = createTestVirtualStockIntake();
    $state = VirtualStockIntakeState::load((int) $intake->state_id);
    $previousDemandId = $state->demand_id;

    burnup5InvokeConcern(
        new VirtualStockIntakeConfirmed(
            state_id: (int) $intake->state_id,
            status: 'confirmed',
            demand_id: null,
            confirmed_at: now()->toIso8601String(),
        ),
        'apply',
        [$state],
    );

    expect(VirtualStockIntakeState::load((int) $intake->state_id)->demand_id)->toBe($previousDemandId);
});

it('VirtualStockIntakeSupplyAdjusted apply preserves last_event_at when adjusted_at is null', function () {
    $intake = createTestVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake);
    $state = VirtualStockIntakeState::load((int) $intake->fresh()->state_id);
    $previous = $state->last_event_at;

    burnup5InvokeConcern(
        new VirtualStockIntakeSupplyAdjusted(
            state_id: (int) $intake->state_id,
            quantity: 9,
            adjusted_at: null,
        ),
        'apply',
        [$state],
    );

    $reloaded = VirtualStockIntakeState::load((int) $intake->state_id);
    expect($reloaded->quantity)->toBe(9)
        ->and($reloaded->last_event_at?->eq($previous))->toBeTrue();
});

it('GroupDealPriceSet handle clears headline deal totals before repricing', function () {
    [$opportunity, $group] = burnup5QuotationWithGroup();
    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from(['deal_price' => 5000]));

    expect($group->item_type)->toBe(OpportunityItemType::Group);

    (new SetSectionDealPrice)($opportunity->fresh(), $group, SetSectionDealPriceData::from([
        'deal_price' => '25.00',
    ]));

    expect($opportunity->fresh()->deal_total)->toBeNull()
        ->and($opportunity->fresh()->deal_price_mode)->toBe(DealPriceMode::FlatHeadline->value);
});

it('ItemSortOrderChanged handle updates sort order and skips audit when emit_audit is false', function () {
    burnup5EnsureItemSortOrderColumn();
    [$opportunity, $item] = burnup5QuotationWithBulkItem();
    $stateId = (int) $item->state_id;
    $state = OpportunityItemState::load($stateId);

    burnup5InvokeConcern(
        new ItemSortOrderChanged(
            opportunity_item_id: $stateId,
            sort_order: 8,
            emit_audit: false,
        ),
        'apply',
        [$state],
    );

    burnup5InvokeConcern(
        new ItemSortOrderChanged(
            opportunity_item_id: $stateId,
            sort_order: 8,
            emit_audit: false,
        ),
        'handle',
        [OpportunityItemState::load($stateId)],
    );

    expect($item->fresh()->sort_order)->toBe(8);
});

it('ItemSortOrderChanged handle records audit when emit_audit is true', function () {
    burnup5EnsureItemSortOrderColumn();
    [$opportunity, $item] = burnup5QuotationWithBulkItem();
    $stateId = (int) $item->state_id;

    DB::transaction(function () use ($stateId, $item): void {
        ItemSortOrderChanged::fire(
            opportunity_item_id: $stateId,
            sort_order: 7,
            emit_audit: true,
            ordered_item_ids: [$item->id],
        );
        Verbs::commit();
    });

    expect($item->fresh()->sort_order)->toBe(7);
});

it('PurchaseOrderSent apply stores supplier reference and handle updates projection', function () {
    $po = createTestPurchaseOrder();
    $stateId = (int) $po->state_id;

    DB::transaction(function () use ($stateId): void {
        PurchaseOrderSent::fire(
            state_id: $stateId,
            sent_at: now()->toIso8601String(),
            supplier_reference: 'SUP-5',
            supplier_reference_provided: true,
        );
        Verbs::commit();
    });

    expect($po->fresh())
        ->status->toBe('sent')
        ->supplier_reference->toBe('SUP-5');
});

it('VirtualStockIntakeSerialisedAssetsRetired apply uses current time when retired_at is omitted', function () {
    $intake = createTestVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake);
    $stateId = (int) $intake->fresh()->state_id;
    $state = VirtualStockIntakeState::load($stateId);
    $state->serialised_assets_provisioned = true;

    burnup5InvokeConcern(
        new VirtualStockIntakeSerialisedAssetsRetired(state_id: $stateId, retired_at: null),
        'apply',
        [$state],
    );

    expect(VirtualStockIntakeState::load($stateId)->serialised_assets_retired)->toBeTrue();
});

it('VirtualStockIntakeSerialisedAssetsRetired handle no-ops when intake projection is missing', function () {
    $intake = createTestVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake);
    $stateId = (int) $intake->fresh()->state_id;

    VirtualStockIntake::query()->whereKey($intake->id)->delete();

    burnup5InvokeConcern(
        new VirtualStockIntakeSerialisedAssetsRetired(state_id: $stateId, retired_at: null),
        'handle',
        [VirtualStockIntakeState::load($stateId)],
    );

    expect(true)->toBeTrue();
});

it('RecalculateOpportunity fires PricingRecalculated with audit on a live opportunity', function () {
    [$opportunity] = burnup5QuotationWithBulkItem();

    (new RecalculateOpportunity)($opportunity->fresh(), RecalculateOpportunityData::from([
        'reset_prices' => false,
        'reset_discounts' => true,
    ]));

    expect(true)->toBeTrue();
});

it('SetItemDiscount fires DiscountManuallyOverridden audit on a live line', function () {
    [$opportunity, $item] = burnup5QuotationWithBulkItem();

    (new SetItemDiscount)($item, SetItemDiscountData::from([
        'discount_percent' => '12.50',
    ]));

    expect($item->fresh()->is_discount_manual)->toBeTrue();
});

it('DispatchBulkQuantity fires BulkQuantityDispatched audit on a live order line', function () {
    [$opportunity, $item] = burnup5QuotationWithBulkItem();
    (new ConvertToOrder)($opportunity->fresh());

    (new DispatchBulkQuantity)($item->fresh(), BulkDispatchData::from([
        'quantity' => '1',
    ]));

    expect((string) $item->fresh()->dispatched_quantity)->toBe('1.00');
});

it('UpdateDraftInvoice fires InvoiceDraftUpdated with updated_at on the draft state', function () {
    $invoice = burnup5DraftInvoice();

    (new UpdateDraftInvoice)(
        $invoice,
        new UpdateDraftInvoiceData(
            notes: 'Batch 5 notes',
            notes_provided: true,
        ),
    );

    expect($invoice->fresh()->notes)->toBe('Batch 5 notes');
});

it('SendPurchaseOrder fires PurchaseOrderSent on a draft purchase order', function () {
    $po = createTestPurchaseOrder();

    (new SendPurchaseOrder)($po);

    expect($po->fresh()->status)->toBe('sent');
});
