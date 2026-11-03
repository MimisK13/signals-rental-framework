<?php

use App\Domain\Invoices\Actions\CreateDraftInvoice;
use App\Domain\Invoices\Actions\GenerateInvoiceFromOpportunity;
use App\Domain\Invoices\Actions\IssueCreditNote;
use App\Domain\Invoices\Actions\IssueInvoice;
use App\Domain\Invoices\Actions\RecordPayment;
use App\Domain\Invoices\Actions\UpdateDraftInvoice;
use App\Domain\Invoices\Data\CreateDraftInvoiceData;
use App\Domain\Invoices\Data\GenerateInvoiceFromOpportunityData;
use App\Domain\Invoices\Data\IssueCreditNoteData;
use App\Domain\Invoices\Data\RecordPaymentData;
use App\Domain\Invoices\Data\UpdateDraftInvoiceData;
use App\Domain\Invoices\Enums\PaymentMethod;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Verbs\Events\Concerns\RecordsInvoiceAudit;
use App\Domain\Invoices\Verbs\Events\CreditNoteIssued;
use App\Domain\Invoices\Verbs\Events\PaymentRecorded;
use App\Domain\Members\Models\Member;
use App\Domain\Pricing\Enums\PricingRoundingDirection;
use App\Domain\Pricing\Models\Surcharge;
use App\Domain\Pricing\Models\SurchargeGroup;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\ActivateVersion;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\AllocateAsset;
use App\Domain\Projects\Opportunities\Actions\ApplyDiscountCategory;
use App\Domain\Projects\Opportunities\Actions\ChangeOpportunityStatus;
use App\Domain\Projects\Opportunities\Actions\ClearSectionDealPrice;
use App\Domain\Projects\Opportunities\Actions\ConvertToOrder;
use App\Domain\Projects\Opportunities\Actions\ConvertToQuotation;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\CreateVersion;
use App\Domain\Projects\Opportunities\Actions\DeleteVersion;
use App\Domain\Projects\Opportunities\Actions\DispatchAsset;
use App\Domain\Projects\Opportunities\Actions\DispatchBulkQuantity;
use App\Domain\Projects\Opportunities\Actions\OverrideOpportunityItemSurcharge;
use App\Domain\Projects\Opportunities\Actions\RecalculateOpportunity;
use App\Domain\Projects\Opportunities\Actions\ReturnBulkQuantity;
use App\Domain\Projects\Opportunities\Actions\RevertAssetStatus;
use App\Domain\Projects\Opportunities\Actions\SetDealPrice;
use App\Domain\Projects\Opportunities\Actions\SetItemDiscount;
use App\Domain\Projects\Opportunities\Actions\SetSectionDealPrice;
use App\Domain\Projects\Opportunities\Actions\SubstituteAsset;
use App\Domain\Projects\Opportunities\Actions\ToggleOpportunitySurchargeGroup;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\AllocateAssetData;
use App\Domain\Projects\Opportunities\Data\ApplyDiscountCategoryData;
use App\Domain\Projects\Opportunities\Data\BulkDispatchData;
use App\Domain\Projects\Opportunities\Data\BulkReturnData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\CreateVersionData;
use App\Domain\Projects\Opportunities\Data\DispatchAssetData;
use App\Domain\Projects\Opportunities\Data\OverrideOpportunityItemSurchargeData;
use App\Domain\Projects\Opportunities\Data\RecalculateOpportunityData;
use App\Domain\Projects\Opportunities\Data\RevertAssetStatusData;
use App\Domain\Projects\Opportunities\Data\SetDealPriceData;
use App\Domain\Projects\Opportunities\Data\SetItemDiscountData;
use App\Domain\Projects\Opportunities\Data\SetSectionDealPriceData;
use App\Domain\Projects\Opportunities\Data\SubstituteAssetData;
use App\Domain\Projects\Opportunities\Data\ToggleOpportunitySurchargeGroupData;
use App\Domain\Projects\Opportunities\Enums\OpportunityStatus;
use App\Domain\Projects\Opportunities\Enums\VersionType;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Projects\Opportunities\Models\OpportunityVersion;
use App\Domain\Projects\Opportunities\Verbs\Events\Concerns\RecordsOpportunityAudit;
use App\Domain\Projects\Opportunities\Verbs\Events\DiscountReset;
use App\Domain\Projects\Opportunities\Verbs\Events\OpportunityConvertedToOrder;
use App\Domain\Projects\Opportunities\Verbs\Events\OpportunityDeleted;
use App\Domain\Projects\Opportunities\Verbs\Events\OpportunityStatusChanged;
use App\Domain\PurchaseOrders\Actions\CancelPurchaseOrder;
use App\Domain\PurchaseOrders\Actions\CreatePurchaseOrder;
use App\Domain\PurchaseOrders\Actions\SendPurchaseOrder;
use App\Domain\PurchaseOrders\Data\CreatePurchaseOrderData;
use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Domain\PurchaseOrders\Verbs\Events\PurchaseOrderCancelled;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Domain\VirtualStock\Actions\CancelVirtualStockIntake;
use App\Domain\VirtualStock\Actions\ConfirmVirtualStockIntake;
use App\Domain\VirtualStock\Actions\ReceiveVirtualStockIntake;
use App\Domain\VirtualStock\Actions\ReturnVirtualStockIntake;
use App\Domain\VirtualStock\Data\ReturnVirtualStockIntakeData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\DealPriceMode;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;

beforeEach(function () {
    Queue::fake();
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

function batch1IssuedInvoice(): Invoice
{
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    return $invoice->refresh();
}

function batch1DraftInvoice(): Invoice
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

function batch1QuotationWithBulkItem(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Batch 1 verbs',
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

function latestVerbEvent(string $type): VerbEvent
{
    return VerbEvent::query()->where('type', $type)->latest('id')->firstOrFail();
}

function invokeProtected(object $object, string $method): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object);
}

it('memoizes invoice audit actor metadata on repeated reads', function () {
    $invoice = batch1IssuedInvoice();
    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => min(100, (int) $invoice->amount_outstanding),
            'payment_method' => PaymentMethod::Cash->value,
        ]),
    );

    $hydrated = latestVerbEvent(PaymentRecorded::class)->event();

    expect($hydrated)->toBeInstanceOf(PaymentRecorded::class)
        ->and(invokeProtected($hydrated, 'invoiceAuditActor'))
        ->toBe(invokeProtected($hydrated, 'invoiceAuditActor'));
});

it('memoizes opportunity audit actor metadata on repeated reads', function () {
    $opportunity = batch1QuotationWithBulkItem();

    (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::QuotationReserved);

    $hydrated = latestVerbEvent(OpportunityStatusChanged::class)->event();

    expect($hydrated)->toBeInstanceOf(OpportunityStatusChanged::class)
        ->and(invokeProtected($hydrated, 'auditActor'))
        ->toBe(invokeProtected($hydrated, 'auditActor'));
});

it('updates only the draft invoice reference when reference_provided is set alone', function () {
    $invoice = batch1DraftInvoice();

    (new UpdateDraftInvoice)(
        $invoice,
        new UpdateDraftInvoiceData(
            reference: 'PO-ONLY',
            reference_provided: true,
        ),
    );

    $invoice->refresh();

    expect($invoice->reference)->toBe('PO-ONLY')
        ->and($invoice->notes)->toBe('Original notes');
});

it('updates only draft invoice notes when notes_provided is set alone', function () {
    $invoice = batch1DraftInvoice();

    (new UpdateDraftInvoice)(
        $invoice,
        new UpdateDraftInvoiceData(
            notes: 'Revised notes',
            notes_provided: true,
        ),
    );

    $invoice->refresh();

    expect($invoice->notes)->toBe('Revised notes')
        ->and($invoice->reference)->toBeNull();
});

it('replays credit note issue when the invoice row was deleted before handle', function () {
    $invoice = batch1IssuedInvoice();

    (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from(['lines' => null]),
    );

    $invoiceId = $invoice->id;
    Invoice::query()->whereKey($invoiceId)->forceDelete();

    Verbs::replay();

    expect(Invoice::query()->whereKey($invoiceId)->exists())->toBeTrue();
});

it('no-ops discount reset handle when the opportunity projection is missing', function () {
    $opportunity = batch1QuotationWithBulkItem();
    $stateId = $opportunity->state_id;

    Opportunity::query()->whereKey($opportunity->id)->forceDelete();

    DB::transaction(function () use ($stateId): void {
        DiscountReset::fire(
            opportunity_id: $stateId,
            applications: [],
        );
        Verbs::commit();
    });

    expect(true)->toBeTrue();
});

it('no-ops opportunity deleted handle when the projection row is already gone', function () {
    $opportunity = batch1QuotationWithBulkItem();
    $stateId = $opportunity->state_id;

    Opportunity::query()->whereKey($opportunity->id)->forceDelete();

    expect(fn () => DB::transaction(function () use ($stateId): void {
        OpportunityDeleted::fire(opportunity_id: $stateId);
        Verbs::commit();
    }))->toThrow(ModelNotFoundException::class);
});

it('covers purchase order cancelled apply when cancelled_at is null', function () {
    $po = createTestPurchaseOrder();
    (new SendPurchaseOrder)($po->refresh());

    DB::transaction(function () use ($po): void {
        PurchaseOrderCancelled::fire(
            state_id: (int) $po->fresh()->state_id,
            status: 'cancelled',
            cancelled_at: null,
        );
        Verbs::commit();
    });

    expect($po->fresh()->status)->toBe('cancelled')
        ->and($po->fresh()->cancelled_at)->toBeNull();
});

it('appends a cancellation reason onto empty purchase order notes', function () {
    $po = createTestPurchaseOrder(['notes' => null]);
    (new SendPurchaseOrder)($po->refresh());

    (new CancelPurchaseOrder)($po->refresh(), 'Supplier withdrew');

    expect($po->fresh()->notes)->toBe('Cancellation: Supplier withdrew');
});

it('appends a cancellation reason onto existing virtual stock intake notes', function () {
    $intake = createTestVirtualStockIntake(['notes' => 'Existing note']);
    app(ConfirmVirtualStockIntake::class)($intake);

    app(CancelVirtualStockIntake::class)($intake->refresh(), 'No longer required');

    expect($intake->fresh()->notes)->toContain('Existing note')
        ->and($intake->fresh()->notes)->toContain('Cancellation: No longer required');
});

it('returns received virtual stock intake through the returned handle path', function () {
    $intake = createTestVirtualStockIntake();
    app(ConfirmVirtualStockIntake::class)($intake);
    app(ReceiveVirtualStockIntake::class)($intake->refresh());

    app(ReturnVirtualStockIntake::class)(
        $intake->refresh(),
        ReturnVirtualStockIntakeData::from(['force' => true]),
    );

    expect($intake->fresh()->status)->toBe('returned');
});

it('creates purchase orders with line items through the genesis handle path', function () {
    $created = (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
        'supplier_id' => Member::factory()->organisation()->create()->id,
        'store_id' => Store::factory()->create()->id,
        'lines' => [[
            'description' => 'Batch line',
            'quantity' => '2.00',
            'unit_cost' => 500,
        ]],
    ]));

    $po = PurchaseOrder::query()->findOrFail($created->id);

    expect($po->items)->toHaveCount(1)
        ->and($po->items->first()->description)->toBe('Batch line');
});

it('runs common opportunity fulfilment and pricing verb handles through actions', function () {
    $opportunity = batch1QuotationWithBulkItem();
    $bulkProductId = (int) $opportunity->items()->firstOrFail()->itemable_id;

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Section',
        'item_type' => 'group',
    ]));
    $opportunity = $opportunity->fresh();
    $group = $opportunity->items->first(fn (OpportunityItem $item): bool => $item->item_type->value === 'group');
    $groupedProduct = Product::factory()->rental()->bulk()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $groupedProduct->name,
        'itemable_id' => $groupedProduct->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ]));
    $groupedLine = $opportunity->fresh()->items()->latest('id')->firstOrFail();
    $groupedLine->forceFill(['path' => $group->path.'0001'])->saveQuietly();

    $opportunity->fresh()->forceFill([
        'exchange_rate_locked' => false,
        'tax_locked' => false,
    ])->saveQuietly();

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '99.99',
        'deal_price_mode' => DealPriceMode::FlatHeadline->value,
    ]));

    (new SetSectionDealPrice)($opportunity->fresh(), $group->fresh(), SetSectionDealPriceData::from([
        'deal_price' => '50.00',
    ]));
    (new ClearSectionDealPrice)($opportunity->fresh(), $group->fresh());

    $discardVersion = OpportunityVersion::query()->whereKey(
        (new CreateVersion)($opportunity->fresh(), CreateVersionData::from([
            'version_type' => VersionType::Alternative->value,
        ]))->id,
    )->firstOrFail();
    $altVersion = OpportunityVersion::query()->whereKey(
        (new CreateVersion)($opportunity->fresh(), CreateVersionData::from([
            'version_type' => VersionType::Alternative->value,
        ]))->id,
    )->firstOrFail();
    (new ActivateVersion)($altVersion);
    (new DeleteVersion)($discardVersion);

    (new ApplyDiscountCategory)($opportunity->fresh(), ApplyDiscountCategoryData::from([]));

    $serialProduct = Product::factory()->rental()->serialised()->create();
    $stock = StockLevel::factory()->serialised()->create([
        'product_id' => $serialProduct->id,
        'store_id' => $opportunity->store_id,
    ]);
    $replacement = StockLevel::factory()->serialised()->create([
        'product_id' => $serialProduct->id,
        'store_id' => $opportunity->store_id,
    ]);

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $serialProduct->name,
        'itemable_id' => $serialProduct->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
    ]));
    (new ConvertToOrder)($opportunity->fresh());

    $opportunity = $opportunity->fresh();
    $serialItem = $opportunity->items()->where('itemable_id', $serialProduct->id)->firstOrFail();
    (new AllocateAsset)($serialItem, AllocateAssetData::from(['stock_level_id' => $stock->id]));
    $asset = OpportunityItemAsset::query()->where('opportunity_item_id', $serialItem->id)->firstOrFail();

    (new DispatchAsset)($asset->fresh(), DispatchAssetData::from([]));
    (new RevertAssetStatus)($asset->fresh(), RevertAssetStatusData::from([
        'revert_to' => AssetAssignmentStatus::Allocated->value,
    ]));
    (new SubstituteAsset)($asset->fresh(), SubstituteAssetData::from([
        'new_stock_level_id' => $replacement->id,
    ]));

    (new DispatchBulkQuantity)(
        $opportunity->fresh()->items()->where('itemable_id', $bulkProductId)->firstOrFail(),
        BulkDispatchData::from(['quantity' => '1']),
    );
    (new ReturnBulkQuantity)(
        $opportunity->fresh()->items()->where('itemable_id', $bulkProductId)->firstOrFail(),
        BulkReturnData::from(['quantity' => '1']),
    );

    (new RecalculateOpportunity)($opportunity->fresh(), RecalculateOpportunityData::from([
        'reset_prices' => false,
        'reset_discounts' => true,
    ]));

    (new SetItemDiscount)(
        $opportunity->fresh()->items()->where('itemable_id', $bulkProductId)->firstOrFail(),
        SetItemDiscountData::from([
            'discount_percent' => '10.00',
        ]),
    );

    $surchargeGroup = SurchargeGroup::factory()->create();
    $surcharge = Surcharge::factory()->for($surchargeGroup, 'surchargeGroup')->percent('10.00')->create();
    (new ToggleOpportunitySurchargeGroup)($opportunity->fresh(), ToggleOpportunitySurchargeGroupData::from([
        'surcharge_group_id' => $surchargeGroup->id,
        'is_active' => true,
    ]));

    $bulkItem = $opportunity->fresh()->items()->where('itemable_id', $bulkProductId)->firstOrFail();
    (new OverrideOpportunityItemSurcharge)($opportunity->fresh(), $bulkItem, OverrideOpportunityItemSurchargeData::from([
        'surcharge_id' => $surcharge->id,
        'amount_minor' => 500,
    ]));

    expect($opportunity->fresh()->items()->count())->toBeGreaterThan(0);
});

it('uses invoice and opportunity audit traits on committed events', function () {
    $invoice = batch1IssuedInvoice();
    (new IssueCreditNote)($invoice, IssueCreditNoteData::from(['lines' => null]));

    $creditEvent = latestVerbEvent(CreditNoteIssued::class)->event();
    expect(in_array(RecordsInvoiceAudit::class, class_uses_recursive($creditEvent)))->toBeTrue();

    $opportunity = batch1QuotationWithBulkItem();
    (new ConvertToOrder)($opportunity->fresh());

    $orderEvent = latestVerbEvent(OpportunityConvertedToOrder::class)->event();
    expect(in_array(RecordsOpportunityAudit::class, class_uses_recursive($orderEvent)))->toBeTrue();
});
