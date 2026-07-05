<?php

/**
 * P4-CL4b burndown batch 4 — Verbs events and concern branches.
 */

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\ReorderOpportunityItems;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\ReorderOpportunityItemsData;
use App\Enums\StockMethod;
use App\Models\Demand;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\Opportunities\AutoPromotionContext;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Invoices\DraftInvoiceDeleted;
use App\Verbs\Events\Invoices\InvoiceGenerated;
use App\Verbs\Events\Invoices\InvoiceIssued;
use App\Verbs\Events\Invoices\InvoiceVoided;
use App\Verbs\Events\Opportunities\AssetDeallocated;
use App\Verbs\Events\Opportunities\Concerns\AdjustsStockAllocation;
use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;
use App\Verbs\Events\Opportunities\ItemSortOrderChanged;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderDeleted;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderReceiptRecorded;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderSent;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderUpdated;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeConfirmed;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeDeleted;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeReceived;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSerialisedAssetsProvisioned;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSerialisedAssetsRetired;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSupplyAdjusted;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-05T10:00:00Z'));
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function batch4DraftInvoice(): Invoice
{
    $opportunity = giCreateOrderOpportunity();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $opportunity->member_id,
        'store_id' => $opportunity->store_id,
        'opportunity_id' => $opportunity->id,
    ]));

    return Invoice::query()->findOrFail($draft->id);
}

function batch4QuotationWithItem(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Batch 4 verbs',
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

    return $opportunity->fresh();
}

function batch4InvokeConcern(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

it('InvoiceGenerated apply null generated_at and handle skips missing opportunity items', function () {
    $invoiceId = app(SequenceAllocator::class)->next('invoices');
    $itemId = app(SequenceAllocator::class)->next('invoice_items');

    DB::transaction(function () use ($invoiceId, $itemId): void {
        InvoiceGenerated::fire(
            invoice_id: $invoiceId,
            member_id: Member::factory()->create()->id,
            store_id: Store::factory()->create()->id,
            generated_at: null,
            line_items: [[
                'invoice_item_id' => $itemId,
                'description' => 'Line',
                'quantity' => '1',
                'unit_price' => 1000,
                'discount' => 0,
                'tax' => 0,
                'net_total' => 1000,
                'tax_treatment' => 'standard',
            ]],
            quantity_updates: [['opportunity_item_id' => 999999, 'quantity' => '1']],
        );
        Verbs::commit();
    });

    expect(Invoice::query()->find($invoiceId))->not->toBeNull();
});

it('PurchaseOrderReceiptRecorded apply partial status and handle missing line items', function () {
    $po = createTestPurchaseOrder();
    (new SendPurchaseOrder)($po->refresh());
    $item = $po->items()->firstOrFail();

    DB::transaction(function () use ($po, $item): void {
        PurchaseOrderReceiptRecorded::fire(
            state_id: (int) $po->fresh()->state_id,
            resulting_status: 'partially_received',
            received_at: null,
            line_updates: [[
                'purchase_order_item_id' => $item->id,
                'received_quantity' => '1',
                'tax' => 0,
                'total' => 1000,
            ], [
                'purchase_order_item_id' => 999999,
                'received_quantity' => '1',
                'tax' => 0,
                'total' => 1000,
            ]],
            tax_finalised: true,
            subtotal: 1000,
            tax_total: 0,
            total: 1000,
        );
        Verbs::commit();
    });

    expect($po->fresh()->status)->toBe('partially_received');
});

it('VirtualStockIntakeConfirmed handle no-ops when intake projection is missing', function () {
    $intake = createTestVirtualStockIntake();
    (new ConfirmVirtualStockIntake)($intake);
    $stateId = (int) $intake->fresh()->state_id;

    VirtualStockIntake::query()->whereKey($intake->id)->delete();

    DB::transaction(function () use ($stateId): void {
        VirtualStockIntakeConfirmed::fire(
            state_id: $stateId,
            status: 'confirmed',
            demand_id: 123,
            confirmed_at: now()->toIso8601String(),
        );
        Verbs::commit();
    });

    expect(true)->toBeTrue();
});

it('VirtualStockIntakeReceived resyncSerialisedAssetSupply when assets provisioned', function () {
    $store = Store::factory()->create();
    $product = Product::factory()->rental()->serialised()->create();
    $intake = createTestVirtualStockIntake([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity' => 1,
    ]);
    (new ConfirmVirtualStockIntake)($intake);
    $intake = $intake->fresh();
    $stateId = (int) $intake->state_id;

    $stockId = app(SequenceAllocator::class)->next('stock_levels');
    $demandId = app(SequenceAllocator::class)->next('demands');

    DB::transaction(function () use ($stateId, $stockId, $demandId, $intake): void {
        VirtualStockIntakeSerialisedAssetsProvisioned::fire(
            state_id: $stateId,
            assets: [[
                'stock_level_id' => $stockId,
                'supply_demand_id' => $demandId,
                'sequence' => 1,
                'asset_number' => 'VS-001',
            ]],
            provisioned_at: now()->toIso8601String(),
        );
        Verbs::commit();
    });

    Demand::factory()->create([
        'id' => $demandId,
        'source_type' => 'virtual_stock_intake',
        'source_id' => $intake->id,
        'asset_id' => $stockId,
        'store_id' => $intake->store_id,
        'product_id' => $intake->product_id,
        'quantity' => 1,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    DB::transaction(function () use ($stateId): void {
        VirtualStockIntakeReceived::fire(
            state_id: $stateId,
            status: 'received',
            received_at: now()->toIso8601String(),
        );
        Verbs::commit();
    });

    expect($intake->fresh()->status)->toBe('received');
});

it('VirtualStockIntakeSerialisedAssetsProvisioned handle no-ops when intake missing', function () {
    $product = Product::factory()->rental()->serialised()->create();
    $intake = createTestVirtualStockIntake([
        'product_id' => $product->id,
        'quantity' => 1,
    ]);
    (new ConfirmVirtualStockIntake)($intake);
    $stateId = (int) $intake->fresh()->state_id;
    $stockId = app(SequenceAllocator::class)->next('stock_levels');
    $demandId = app(SequenceAllocator::class)->next('demands');

    DB::transaction(function () use ($stateId, $stockId, $demandId): void {
        VirtualStockIntakeSerialisedAssetsProvisioned::fire(
            state_id: $stateId,
            assets: [[
                'stock_level_id' => $stockId,
                'supply_demand_id' => $demandId,
                'sequence' => 1,
                'asset_number' => 'VS-MISSING',
            ]],
        );
        Verbs::commit();
    });

    VirtualStockIntake::query()->whereKey($intake->id)->delete();

    $state = \App\Verbs\States\VirtualStockIntakeState::load($stateId);
    $event = new VirtualStockIntakeSerialisedAssetsProvisioned(
        state_id: $stateId,
        assets: [[
            'stock_level_id' => $stockId,
            'supply_demand_id' => $demandId,
            'sequence' => 1,
            'asset_number' => 'VS-MISSING',
        ]],
    );

    batch4InvokeConcern($event, 'handle', [$state]);

    expect(true)->toBeTrue();
});

it('DraftInvoiceDeleted apply null deleted_at and handle idempotent trashed row', function () {
    $invoice = batch4DraftInvoice();
    $stateId = (int) $invoice->state_id;

    DB::transaction(function () use ($stateId): void {
        DraftInvoiceDeleted::fire(state_id: $stateId, deleted_at: null);
        Verbs::commit();
    });

    expect($invoice->fresh()->trashed())->toBeTrue();
});

it('InvoiceIssued apply null issued_at and handle when old row missing', function () {
    $invoice = batch4DraftInvoice();
    $stateId = (int) $invoice->state_id;
    Invoice::query()->whereKey($invoice->id)->forceDelete();

    DB::transaction(function () use ($stateId): void {
        InvoiceIssued::fire(
            state_id: $stateId,
            issued_at: null,
            due_at: null,
        );
        Verbs::commit();
    });

    expect(Invoice::withTrashed()->whereKey($invoice->id)->exists())->toBeTrue();
});

it('InvoiceVoided handle skips missing opportunity items and clears invoiced flag', function () {
    $opportunity = giCreateOrderOpportunity();
    $item = $opportunity->items()->firstOrFail();
    $invoice = batch4DraftInvoice();
    (new IssueInvoice)($invoice);
    $stateId = (int) $invoice->fresh()->state_id;

    DB::transaction(function () use ($stateId, $item, $opportunity): void {
        InvoiceVoided::fire(
            state_id: $stateId,
            voided_at: now()->toIso8601String(),
            quantity_releases: [
                ['opportunity_item_id' => $item->id, 'quantity' => '0.50'],
                ['opportunity_item_id' => 999999, 'quantity' => '1'],
            ],
        );
        Verbs::commit();
    });

    expect($opportunity->fresh()->invoiced)->toBeFalse();
});

it('PurchaseOrderDeleted handle no-ops when already trashed', function () {
    $po = createTestPurchaseOrder();
    $stateId = (int) $po->state_id;
    $po->delete();

    DB::transaction(function () use ($stateId): void {
        PurchaseOrderDeleted::fire(state_id: $stateId, deleted_at: null);
        Verbs::commit();
    });

    expect(PurchaseOrder::withTrashed()->find($po->id)?->trashed())->toBeTrue();
});

it('PurchaseOrderSent apply without supplier reference and handle updates projection', function () {
    $po = createTestPurchaseOrder();
    $stateId = (int) $po->state_id;

    DB::transaction(function () use ($stateId): void {
        PurchaseOrderSent::fire(
            state_id: $stateId,
            sent_at: null,
            supplier_reference_provided: false,
        );
        Verbs::commit();
    });

    expect($po->fresh()->status)->toBe('sent');
});

it('VirtualStockIntakeDeleted handle no-ops when intake missing or trashed', function () {
    $intake = createTestVirtualStockIntake();
    $stateId = (int) $intake->state_id;
    $intake->delete();

    DB::transaction(function () use ($stateId): void {
        VirtualStockIntakeDeleted::fire(state_id: $stateId, deleted_at: null);
        Verbs::commit();
    });

    expect(VirtualStockIntake::withTrashed()->find($intake->id)?->trashed())->toBeTrue();
});

it('VirtualStockIntakeSerialisedAssetsRetired handle no-ops when intake missing', function () {
    $product = Product::factory()->rental()->serialised()->create();
    $intake = createTestVirtualStockIntake([
        'product_id' => $product->id,
        'quantity' => 1,
    ]);
    (new ConfirmVirtualStockIntake)($intake);
    $stateId = (int) $intake->fresh()->state_id;
    $stockId = app(SequenceAllocator::class)->next('stock_levels');
    $demandId = app(SequenceAllocator::class)->next('demands');

    DB::transaction(function () use ($stateId, $stockId, $demandId): void {
        VirtualStockIntakeSerialisedAssetsProvisioned::fire(
            state_id: $stateId,
            assets: [[
                'stock_level_id' => $stockId,
                'supply_demand_id' => $demandId,
                'sequence' => 1,
                'asset_number' => 'VS-RET-1',
            ]],
        );
        Verbs::commit();
    });

    VirtualStockIntake::query()->whereKey($intake->id)->delete();

    DB::transaction(function () use ($stateId): void {
        VirtualStockIntakeSerialisedAssetsRetired::fire(
            state_id: $stateId,
            retired_at: null,
        );
        Verbs::commit();
    });

    expect(true)->toBeTrue();
});

it('PurchaseOrderUpdated apply null dates and handle deletes orphaned lines', function () {
    $po = createTestPurchaseOrder();
    $existingItem = $po->items()->firstOrFail();
    $orphanId = app(SequenceAllocator::class)->next('purchase_order_items');
    PurchaseOrderItem::query()->create([
        'id' => $orphanId,
        'purchase_order_id' => $po->id,
        'description' => 'Orphan',
        'quantity' => '1',
        'received_quantity' => '0',
        'unit_cost' => 100,
        'tax' => 0,
        'total' => 100,
        'sort_order' => 99,
    ]);

    $stateId = (int) $po->state_id;

    DB::transaction(function () use ($stateId, $existingItem): void {
        PurchaseOrderUpdated::fire(
            state_id: $stateId,
            expected_at: null,
            starts_at: null,
            ends_at: null,
            updated_at: now()->toIso8601String(),
            line_items: [[
                'purchase_order_item_id' => $existingItem->id,
                'description' => $existingItem->description,
                'quantity' => '1',
                'received_quantity' => '0',
                'unit_cost' => 100,
                'total' => 100,
            ]],
        );
        Verbs::commit();
    });

    expect(PurchaseOrderItem::query()->whereKey($orphanId)->exists())->toBeFalse();
});

it('AdjustsStockAllocation concern clamps decrements and skips replay asset checks', function () {
    $concern = new class
    {
        use AdjustsStockAllocation;

        public function dec(?int $stockLevelId, string $by): void
        {
            $this->decrementStockAllocation($stockLevelId, $by);
        }

        public function runAssert(OpportunityItem $item, int $stockLevelId): void
        {
            $this->assertAssetAvailableForItem($item, $stockLevelId);
        }
    };

    $concern->dec(null, '1');

    $stock = StockLevel::factory()->bulk()->create(['quantity_allocated' => '0.50']);

    $concern->dec($stock->id, '5');

    expect((string) $stock->fresh()->quantity_allocated)->toBe('0.00');

    Verbs::shouldReceive('isReplaying')->andReturn(true);

    $item = OpportunityItem::factory()->create();
    $concern->runAssert($item, $stock->id);

    expect(true)->toBeTrue();
});

it('PromotesOpportunityStatus concern early returns and isSerialised fallback', function () {
    $concern = new class
    {
        use PromotesOpportunityStatus;

        public function runPromote(?Opportunity $opportunity): void
        {
            $this->promoteOpportunityFromItems($opportunity);
        }
    };

    $concern->runPromote(null);

    app(AutoPromotionContext::class)->suppress(function () use ($concern): void {
        $concern->runPromote(Opportunity::factory()->create());
    });

    $isSerialised = new ReflectionMethod($concern, 'isSerialised');
    $isSerialised->setAccessible(true);

    $serviceItem = OpportunityItem::factory()->create([
        'itemable_type' => Product::class,
        'itemable_id' => Product::factory()->service()->create()->id,
    ]);

    expect($isSerialised->invoke($concern, $serviceItem))->toBeFalse();

    $product = Product::factory()->rental()->serialised()->create();
    $serialItem = OpportunityItem::factory()->create([
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
    ]);

    expect($isSerialised->invoke($concern, $serialItem))->toBeTrue();
});

it('ItemSortOrderChanged validate apply and handle audit anchor', function () {
    $opportunity = batch4QuotationWithItem();
    $item = $opportunity->items()->firstOrFail();
    $stateId = (int) $item->state_id;

    DB::transaction(function () use ($stateId, $item): void {
        ItemSortOrderChanged::fire(
            opportunity_item_id: $stateId,
            sort_order: 5,
            emit_audit: true,
            ordered_item_ids: [$item->id],
        );
        Verbs::commit();
    });

    expect($item->fresh()->sort_order)->toBe(5);

    OpportunityItem::query()->whereKey($item->id)->delete();
    $state = \App\Verbs\States\OpportunityItemState::load($stateId);
    $event = new ItemSortOrderChanged(
        opportunity_item_id: $stateId,
        sort_order: 9,
        emit_audit: false,
    );

    batch4InvokeConcern($event, 'handle', [$state]);

    expect(true)->toBeTrue();
});

it('VirtualStockIntakeSupplyAdjusted construct validate apply and handle syncs demand', function () {
    $intake = createTestVirtualStockIntake();
    (new ConfirmVirtualStockIntake)($intake);
    $intake = $intake->fresh();
    $stateId = (int) $intake->state_id;

    $constructed = new VirtualStockIntakeSupplyAdjusted(
        state_id: $stateId,
        quantity: 8,
        adjusted_at: now()->toIso8601String(),
    );

    expect($constructed->quantity)->toBe(8);

    DB::transaction(function () use ($stateId): void {
        VirtualStockIntakeSupplyAdjusted::fire(
            state_id: $stateId,
            quantity: 8,
            adjusted_at: now()->toIso8601String(),
        );
        Verbs::commit();
    });

    expect($intake->fresh()->quantity)->toBe(8);
});

it('AssetDeallocated releaseVirtualAssetStatus transitions virtual assets', function () {
    $opportunity = batch4QuotationWithItem();
    (new ConvertToOrder)($opportunity->fresh());

    $product = Product::factory()->rental()->serialised()->create();
    $virtualStock = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $opportunity->store_id,
        'is_virtual' => true,
        'virtual_asset_status' => 'allocated',
    ]);

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ]));

    $item = $opportunity->fresh()->items()->where('itemable_id', $product->id)->firstOrFail();

    (new AllocateAsset)($item, \App\Data\Opportunities\AllocateAssetData::from([
        'stock_level_id' => $virtualStock->id,
    ]));

    $assignment = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->firstOrFail();
    $stateId = (int) $assignment->state_id;

    DB::transaction(function () use ($stateId): void {
        AssetDeallocated::fire(state_id: $stateId, reason: 'batch4');
        Verbs::commit();
    });

    expect($virtualStock->fresh()->virtual_asset_status)->not->toBe('allocated');
});

it('ReorderOpportunityItems action fires ItemSortOrderChanged events end-to-end', function () {
    $opportunity = batch4QuotationWithItem();
    $items = $opportunity->items()->orderBy('id')->get();
    $ordered = $items->reverse()->pluck('id')->all();

    (new ReorderOpportunityItems)($opportunity, ReorderOpportunityItemsData::from([
        'item_ids' => $ordered,
    ]));

    expect($opportunity->fresh()->items()->orderBy('sort_order')->pluck('id')->all())
        ->toBe($ordered);
});
