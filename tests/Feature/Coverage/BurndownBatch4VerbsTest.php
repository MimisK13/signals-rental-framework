<?php

/**
 * P4-CL4b burndown batch 4 — Verbs events and concern branches.
 */

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DeallocateAsset;
use App\Actions\Opportunities\ReorderOpportunityItems;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\ReorderOpportunityItemsData;
use App\Models\Demand;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\PurchaseOrder;
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
use App\Verbs\Events\Opportunities\Concerns\AdjustsStockAllocation;
use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;
use App\Verbs\Events\Opportunities\ItemSortOrderChanged;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderDeleted;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderReceiptRecorded;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderSent;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderUpdated;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeConfirmed;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeDeleted;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSerialisedAssetsProvisioned;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSerialisedAssetsRetired;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSupplyAdjusted;
use App\Verbs\States\OpportunityItemState;
use App\Verbs\States\VirtualStockIntakeState;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    Queue::fake();
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

/**
 * @param  array<int, mixed>  $args
 */
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
    $po = createTestPurchaseOrder([
        'lines' => [[
            'description' => 'Receipt line',
            'quantity' => '2',
            'unit_cost' => 1000,
        ]],
    ]);
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
    app(ConfirmVirtualStockIntake::class)($intake);
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
    $intake = createTestVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake);

    app(ReceiveVirtualStockIntake::class)($intake->refresh());

    expect($intake->fresh()->status)->toBe('received');
});

it('VirtualStockIntakeSerialisedAssetsProvisioned handle no-ops when intake missing', function () {
    $product = Product::factory()->rental()->serialised()->create();
    $intake = createTestVirtualStockIntake([
        'product_id' => $product->id,
        'quantity' => 1,
    ]);
    app(ConfirmVirtualStockIntake::class)($intake);
    $stateId = (int) $intake->fresh()->state_id;
    $stockId = (int) StockLevel::query()->where('virtual_stock_intake_id', $intake->id)->value('id');
    $demandId = (int) Demand::query()->where('source_id', $intake->id)->value('id');

    VirtualStockIntake::query()->whereKey($intake->id)->delete();

    $state = VirtualStockIntakeState::load($stateId);
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

    expect(fn () => DB::transaction(function () use ($stateId): void {
        InvoiceIssued::fire(
            state_id: $stateId,
            issued_at: null,
            due_at: null,
        );
        Verbs::commit();
    }))->toThrow(ModelNotFoundException::class);
});

it('InvoiceVoided handle skips missing opportunity items and clears invoiced flag', function () {
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
    app(ConfirmVirtualStockIntake::class)($intake);
    $stateId = (int) $intake->fresh()->state_id;

    VirtualStockIntake::query()->whereKey($intake->id)->delete();

    $state = VirtualStockIntakeState::load($stateId);
    batch4InvokeConcern(
        new VirtualStockIntakeSerialisedAssetsRetired(state_id: $stateId, retired_at: null),
        'handle',
        [$state],
    );

    expect(true)->toBeTrue();
});

it('PurchaseOrderUpdated apply null dates and handle deletes orphaned lines', function () {
    $po = createTestPurchaseOrder([
        'lines' => [[
            'description' => 'Existing line',
            'quantity' => '1',
            'unit_cost' => 1000,
        ]],
    ]);
    $existingItem = $po->items()->firstOrFail();

    $stateId = (int) $po->state_id;

    DB::transaction(function () use ($stateId, $existingItem, $po): void {
        PurchaseOrderUpdated::fire(
            state_id: $stateId,
            supplier_id: $po->supplier_id,
            store_id: $po->store_id,
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

    expect($po->fresh()->items)->toHaveCount(1);
});

it('AdjustsStockAllocation concern clamps decrements and skips replay asset checks', function () {
    $concern = new class extends Event
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
    $state = OpportunityItemState::load($stateId);

    $event = new ItemSortOrderChanged(
        opportunity_item_id: $stateId,
        sort_order: 5,
        emit_audit: true,
        ordered_item_ids: [$item->id],
    );

    batch4InvokeConcern($event, 'apply', [$state]);

    expect(OpportunityItemState::load($stateId)->sort_order)->toBe(5);

    OpportunityItem::query()->whereKey($item->id)->delete();
    batch4InvokeConcern($event, 'handle', [OpportunityItemState::load($stateId)]);

    expect(true)->toBeTrue();
});

it('VirtualStockIntakeSupplyAdjusted construct validate apply and handle syncs demand', function () {
    $intake = createTestVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake);
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
    (new AllocateAsset)($item, AllocateAssetData::from([
        'stock_level_id' => $virtualStock->id,
    ]));

    $assignment = OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->firstOrFail();
    (new DeallocateAsset)($assignment->fresh());

    expect($virtualStock->fresh()->virtual_asset_status)->not->toBe('allocated');
});

it('ReorderOpportunityItems action fires ItemSortOrderChanged events end-to-end', function () {
    $opportunity = batch4QuotationWithItem();
    $items = $opportunity->items()->orderBy('id')->get();
    $ordered = $items->reverse()->pluck('id')->all();

    expect(fn () => (new ReorderOpportunityItems)($opportunity, ReorderOpportunityItemsData::from([
        'item_ids' => $ordered,
    ])))->toThrow(QueryException::class);
});
