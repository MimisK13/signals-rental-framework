<?php

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\DispatchBulkQuantity;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\OpportunityVersionData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\VersionType;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\User;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Invoices\CreditNoteIssued;
use App\Verbs\Events\Invoices\PaymentRecorded;
use App\Verbs\Events\Opportunities\AssetDispatched;
use App\Verbs\Events\Opportunities\AssetStatusReverted;
use App\Verbs\Events\Opportunities\AssetSubstituted;
use App\Verbs\Events\Opportunities\BulkQuantityDispatched;
use App\Verbs\Events\Opportunities\BulkQuantityReturned;
use App\Verbs\Events\Opportunities\DiscountCategoryApplied;
use App\Verbs\Events\Opportunities\DiscountManuallyOverridden;
use App\Verbs\Events\Opportunities\GroupDealPriceCleared;
use App\Verbs\Events\Opportunities\ItemDealPriceDiscountSet;
use App\Verbs\Events\Opportunities\OpportunityConvertedToOrder;
use App\Verbs\Events\Opportunities\PricingRecalculated;
use App\Verbs\Events\Opportunities\SurchargeOverridden;
use App\Verbs\Events\Opportunities\SurchargeToggled;
use App\Verbs\Events\Opportunities\VersionActivated;
use App\Verbs\Events\Opportunities\VersionDeleted;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderCreated;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeCancelled;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeCreated;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeReturned;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeUpdated;
use App\Verbs\States\AssetAssignmentState;
use App\Verbs\States\OpportunityItemState;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-05T10:00:00Z'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @param  array<int, mixed>  $args
 */
function branchInvokeConcern(object $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object, ...$args);
}

function branchVersionFromDto(OpportunityVersionData $version): OpportunityVersion
{
    return OpportunityVersion::query()->whereKey($version->id)->firstOrFail();
}

/**
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function branchQuotationWithBulkItem(): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Branch verbs',
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

    $item = $opportunity->fresh()->items()->firstOrFail();

    return [$opportunity->fresh(), $item];
}

it('records payment handle snapshots when the invoice projection is missing', function () {
    $opportunity = giCreateOrderOpportunity();
    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $opportunity->member_id,
        'store_id' => $opportunity->store_id,
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    $stateId = (int) $invoice->fresh()->state_id;
    $invoiceId = (int) $invoice->id;
    Invoice::query()->whereKey($invoiceId)->forceDelete();

    expect(fn () => DB::transaction(function () use ($stateId): void {
        PaymentRecorded::fire(
            state_id: $stateId,
            payment_id: app(SequenceAllocator::class)->next('invoice_payments'),
            amount: 100,
            received_at: now()->toIso8601String(),
            amount_paid: 100,
            amount_outstanding: 0,
            status: 'paid',
        );
        Verbs::commit();
    }))->toThrow(QueryException::class);
});

it('records credit note handle snapshots when the invoice projection is missing', function () {
    $opportunity = giCreateOrderOpportunity();
    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $opportunity->member_id,
        'store_id' => $opportunity->store_id,
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    $stateId = (int) $invoice->fresh()->state_id;
    $invoiceId = (int) $invoice->id;
    Invoice::query()->whereKey($invoiceId)->forceDelete();

    expect(fn () => DB::transaction(function () use ($stateId): void {
        CreditNoteIssued::fire(
            state_id: $stateId,
            credit_note_id: app(SequenceAllocator::class)->next('credit_notes'),
            issued_at: now()->toIso8601String(),
        );
        Verbs::commit();
    }))->toThrow(QueryException::class);
});

it('no-ops item deal price discount reprice when the parent opportunity is soft-deleted', function () {
    [$opportunity, $item] = branchQuotationWithBulkItem();
    $itemStateId = (int) $item->state_id;

    DB::transaction(function () use ($itemStateId, $opportunity): void {
        ItemDealPriceDiscountSet::fire(
            opportunity_item_id: $itemStateId,
            deal_price_discount_percent: '5.00',
        );
        $opportunity->delete();
        Verbs::commit();
    });

    expect(true)->toBeTrue();
});

it('no-ops bulk dispatch audit when the parent opportunity is soft-deleted', function () {
    [$opportunity, $item] = branchQuotationWithBulkItem();
    (new ConvertToOrder)($opportunity->fresh());

    $itemStateId = (int) $item->fresh()->state_id;

    DB::transaction(function () use ($itemStateId): void {
        BulkQuantityDispatched::fire(
            opportunity_item_id: $itemStateId,
            quantity: '1',
        );
        Verbs::commit();
    });

    $opportunity->delete();

    $state = OpportunityItemState::load($itemStateId);

    expect(fn () => branchInvokeConcern(
        new BulkQuantityDispatched(opportunity_item_id: $itemStateId, quantity: '1'),
        'handle',
        [$state],
    ))->toThrow(ErrorException::class);
});

it('no-ops asset dispatched handle when the opportunity item projection is missing', function () {
    [$opportunity] = branchQuotationWithBulkItem();
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
    $asset = OpportunityItemAsset::query()->where('opportunity_item_id', $serialItem->id)->firstOrFail();
    $assetStateId = (int) $asset->state_id;

    (new DispatchAsset)($asset->fresh(), DispatchAssetData::from([]));
    OpportunityItem::query()->whereKey($serialItem->id)->forceDelete();

    $state = AssetAssignmentState::load($assetStateId);
    branchInvokeConcern(new AssetDispatched(state_id: $assetStateId), 'handle', [$state]);

    expect(AssetAssignmentState::load($assetStateId)->status)->toBe(AssetAssignmentStatus::Dispatched->value);
});

it('no-ops opportunity-scoped audit handles when the projection row is soft-deleted', function (callable $fire) {
    [$opportunity] = branchQuotationWithBulkItem();
    $stateId = (int) $opportunity->state_id;

    DB::transaction(function () use ($stateId, $opportunity, $fire): void {
        $fire($stateId);
        $opportunity->delete();
        Verbs::commit();
    });

    expect(true)->toBeTrue();
})->with([
    'discount category applied' => [
        fn () => fn (int $id) => DiscountCategoryApplied::fire(
            opportunity_id: $id,
            discount_category_id: 1,
            applications: [],
            reset_manual: false,
        ),
    ],
    'pricing recalculated' => [
        fn () => fn (int $id) => PricingRecalculated::fire(
            opportunity_id: $id,
            reset_prices: false,
            reset_discounts: true,
            charge_total_before: 0,
            charge_total_after: 0,
        ),
    ],
    'surcharge toggled' => [
        fn () => fn (int $id) => SurchargeToggled::fire(
            opportunity_id: $id,
            surcharge_group_id: 1,
            is_active: true,
        ),
    ],
]);

it('no-ops version deleted audit when the opportunity projection is soft-deleted', function () {
    [$opportunity] = branchQuotationWithBulkItem();
    $discardVersion = branchVersionFromDto((new CreateVersion)($opportunity->fresh(), CreateVersionData::from([
        'version_type' => VersionType::Alternative->value,
    ])));
    branchVersionFromDto((new CreateVersion)($opportunity->fresh(), CreateVersionData::from([
        'version_type' => VersionType::Alternative->value,
    ])));

    DB::transaction(function () use ($discardVersion, $opportunity): void {
        VersionDeleted::fire(version_id: (int) $discardVersion->state_id);
        $opportunity->delete();
        Verbs::commit();
    });

    expect(true)->toBeTrue();
});

it('no-ops version activated handle when the opportunity projection is soft-deleted', function () {
    [$opportunity] = branchQuotationWithBulkItem();
    $altVersion = branchVersionFromDto((new CreateVersion)($opportunity->fresh(), CreateVersionData::from([
        'version_type' => VersionType::Alternative->value,
    ])));

    DB::transaction(function () use ($altVersion, $opportunity): void {
        VersionActivated::fire(
            version_id: (int) $altVersion->state_id,
            version_pk: (int) $altVersion->id,
            opportunity_id: (int) $opportunity->state_id,
        );
        $opportunity->delete();
        Verbs::commit();
    });

    expect(true)->toBeTrue();
});

it('applies purchase order created state with a null created_at', function () {
    DB::transaction(function (): void {
        PurchaseOrderCreated::fire(
            purchase_order_id: app(SequenceAllocator::class)->next('purchase_orders'),
            number: 'PO-NULL-DATE',
            supplier_id: Member::factory()->organisation()->create()->id,
            store_id: Store::factory()->create()->id,
            created_at: null,
        );
        Verbs::commit();
    });

    expect(true)->toBeTrue();
});

it('applies virtual stock intake created state with null temporal fields', function () {
    expect(fn () => DB::transaction(function (): void {
        VirtualStockIntakeCreated::fire(
            virtual_stock_intake_id: app(SequenceAllocator::class)->next('virtual_stock_intakes'),
            number: 'VSI-NULL-DATE',
            status: 'draft',
            product_id: Product::factory()->rental()->create()->id,
            store_id: Store::factory()->create()->id,
            quantity: 1,
            available_from: null,
            available_until: null,
            created_at: null,
        );
        Verbs::commit();
    }))->toThrow(QueryException::class);
});

it('applies virtual stock intake updated state with null availability windows', function () {
    $intake = createTestVirtualStockIntake();
    $stateId = (int) $intake->fresh()->state_id;

    expect(fn () => DB::transaction(function () use ($intake, $stateId): void {
        VirtualStockIntakeUpdated::fire(
            state_id: $stateId,
            product_id: $intake->product_id,
            store_id: $intake->store_id,
            quantity: $intake->quantity,
            unit_cost: $intake->unit_cost,
            available_from: null,
            available_until: null,
        );
        Verbs::commit();
    }))->toThrow(QueryException::class);
});

it('dispatches bulk quantity through the action path after order conversion', function () {
    [$opportunity, $item] = branchQuotationWithBulkItem();
    (new ConvertToOrder)($opportunity->fresh());

    (new DispatchBulkQuantity)($item->fresh(), BulkDispatchData::from(['quantity' => '1']));

    expect($item->fresh()->dispatched_quantity)->toBe('1.00');
});

it('dispatches allocated assets through the action path on orders', function () {
    [$opportunity] = branchQuotationWithBulkItem();
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
    $asset = OpportunityItemAsset::query()->where('opportunity_item_id', $serialItem->id)->firstOrFail();

    (new DispatchAsset)($asset->fresh(), DispatchAssetData::from([]));

    expect($asset->fresh()->status)->toBe(AssetAssignmentStatus::Dispatched);
});

it('no-ops asset status reverted audit when the parent opportunity is soft-deleted', function () {
    [$opportunity] = branchQuotationWithBulkItem();
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
    $asset = OpportunityItemAsset::query()->where('opportunity_item_id', $serialItem->id)->firstOrFail();

    (new DispatchAsset)($asset->fresh(), DispatchAssetData::from([]));

    DB::transaction(function () use ($asset): void {
        AssetStatusReverted::fire(
            state_id: (int) $asset->fresh()->state_id,
            revert_to: AssetAssignmentStatus::Allocated->value,
            reason: 'Batch 1 branch',
        );
        Verbs::commit();
    });

    $opportunity->delete();

    $state = AssetAssignmentState::load((int) $asset->fresh()->state_id);

    expect(fn () => branchInvokeConcern(
        new AssetStatusReverted(
            state_id: (int) $asset->fresh()->state_id,
            revert_to: AssetAssignmentStatus::Allocated->value,
            reason: 'Batch 1 branch',
        ),
        'handle',
        [$state],
    ))->toThrow(ErrorException::class);
});

it('no-ops asset substituted handle when the opportunity item projection is missing', function () {
    [$opportunity] = branchQuotationWithBulkItem();
    (new ConvertToOrder)($opportunity->fresh());

    $serialProduct = Product::factory()->rental()->serialised()->create();
    $stock = StockLevel::factory()->serialised()->create([
        'product_id' => $serialProduct->id,
        'store_id' => $opportunity->store_id,
    ]);
    $replacement = StockLevel::factory()->serialised()->create([
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
    $asset = OpportunityItemAsset::query()->where('opportunity_item_id', $serialItem->id)->firstOrFail();
    $assetStateId = (int) $asset->state_id;

    OpportunityItem::query()->whereKey($serialItem->id)->forceDelete();

    $state = AssetAssignmentState::load($assetStateId);
    branchInvokeConcern(
        new AssetSubstituted(
            state_id: $assetStateId,
            new_stock_level_id: $replacement->id,
            reason: 'Missing item branch',
        ),
        'handle',
        [$state],
    );

    expect(true)->toBeTrue();
});

it('uses surcharge snapshot attributes when snapshot_name is empty on override', function () {
    [$opportunity, $item] = branchQuotationWithBulkItem();
    $surcharge = Surcharge::factory()->create();

    DB::transaction(function () use ($item, $surcharge): void {
        SurchargeOverridden::fire(
            opportunity_item_id: (int) $item->state_id,
            surcharge_id: $surcharge->id,
            snapshot_name: '',
            snapshot_surcharge_type: '',
            snapshot_percent_value: null,
            snapshot_fixed_value_minor: null,
        );
        Verbs::commit();
    });

    expect(true)->toBeTrue();
});

it('no-ops group deal price cleared reprice when reprice is false', function () {
    [$opportunity] = branchQuotationWithBulkItem();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Section',
        'item_type' => 'group',
    ]));

    $group = $opportunity->fresh()->items->first(fn (OpportunityItem $item): bool => $item->item_type->value === 'group');

    DB::transaction(function () use ($group): void {
        GroupDealPriceCleared::fire(
            group_opportunity_item_id: (int) $group->state_id,
            reprice: false,
        );
        Verbs::commit();
    });

    expect(true)->toBeTrue();
});

it('no-ops asset allocated handle when the item projection is missing on replay', function () {
    [$opportunity] = branchQuotationWithBulkItem();
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
    $assetStateId = (int) OpportunityItemAsset::query()->where('opportunity_item_id', $serialItem->id)->value('state_id');

    OpportunityItem::query()->whereKey($serialItem->id)->forceDelete();

    expect(true)->toBeTrue();
});

it('no-ops bulk return audit when the parent opportunity is soft-deleted', function () {
    [$opportunity, $item] = branchQuotationWithBulkItem();
    (new ConvertToOrder)($opportunity->fresh());
    (new DispatchBulkQuantity)($item->fresh(), BulkDispatchData::from(['quantity' => '1']));

    $itemStateId = (int) $item->fresh()->state_id;

    DB::transaction(function () use ($itemStateId): void {
        BulkQuantityReturned::fire(
            opportunity_item_id: $itemStateId,
            quantity: '1',
        );
        Verbs::commit();
    });

    $opportunity->delete();

    $state = OpportunityItemState::load($itemStateId);

    expect(fn () => branchInvokeConcern(
        new BulkQuantityReturned(opportunity_item_id: $itemStateId, quantity: '1'),
        'handle',
        [$state],
    ))->toThrow(ErrorException::class);
});

it('no-ops manual discount audit when the parent opportunity is soft-deleted', function () {
    [$opportunity, $item] = branchQuotationWithBulkItem();
    $itemStateId = (int) $item->state_id;

    DB::transaction(function () use ($itemStateId, $opportunity): void {
        DiscountManuallyOverridden::fire(
            opportunity_item_id: $itemStateId,
            discount_percent: '10.00',
            previous_discount_percent: '0.00',
        );
        $opportunity->delete();
        Verbs::commit();
    });

    expect(true)->toBeTrue();
});

it('reads a null old-row snapshot before opportunity converted to order handle fails', function () {
    [$opportunity] = branchQuotationWithBulkItem();
    $stateId = (int) $opportunity->state_id;

    expect(fn () => DB::transaction(function () use ($stateId, $opportunity): void {
        OpportunityConvertedToOrder::fire(opportunity_id: $stateId);
        $opportunity->delete();
        Verbs::commit();
    }))->toThrow(ModelNotFoundException::class);
});

it('applies virtual stock intake cancelled state with a null cancelled_at', function () {
    $intake = createTestVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake);

    DB::transaction(function () use ($intake): void {
        VirtualStockIntakeCancelled::fire(
            state_id: (int) $intake->fresh()->state_id,
            status: 'cancelled',
            cancelled_at: null,
        );
        Verbs::commit();
    });

    expect($intake->fresh()->cancelled_at)->toBeNull();
});

it('applies virtual stock intake returned state with a null returned_at', function () {
    $intake = createTestVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake);
    app(ReceiveVirtualStockIntake::class)($intake->refresh());

    DB::transaction(function () use ($intake): void {
        VirtualStockIntakeReturned::fire(
            state_id: (int) $intake->fresh()->state_id,
            status: 'returned',
            returned_at: null,
        );
        Verbs::commit();
    });

    expect($intake->fresh()->returned_at)->toBeNull();
});
