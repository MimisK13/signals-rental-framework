<?php

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Invoices\UpdateDraftInvoice;
use App\Actions\Opportunities\ActivateVersion;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ApplyDiscountCategory;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ClearSectionDealPrice;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Actions\Opportunities\DeleteVersion;
use App\Actions\Opportunities\DispatchAsset;
use App\Actions\Opportunities\DispatchBulkQuantity;
use App\Actions\Opportunities\OverrideOpportunityItemSurcharge;
use App\Actions\Opportunities\RecalculateOpportunity;
use App\Actions\Opportunities\ReturnBulkQuantity;
use App\Actions\Opportunities\RevertAssetStatus;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetItemDiscount;
use App\Actions\Opportunities\SetSectionDealPrice;
use App\Actions\Opportunities\SubstituteAsset;
use App\Actions\Opportunities\ToggleOpportunitySurchargeGroup;
use App\Actions\PurchaseOrders\CancelPurchaseOrder;
use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Actions\VirtualStock\CancelVirtualStockIntake;
use App\Actions\VirtualStock\ConfirmVirtualStockIntake;
use App\Actions\VirtualStock\ReceiveVirtualStockIntake;
use App\Actions\VirtualStock\ReturnVirtualStockIntake;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Data\Invoices\RecordPaymentData;
use App\Data\Invoices\UpdateDraftInvoiceData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\ApplyDiscountCategoryData;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\BulkReturnData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Data\Opportunities\DispatchAssetData;
use App\Data\Opportunities\OverrideOpportunityItemSurchargeData;
use App\Data\Opportunities\RecalculateOpportunityData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Data\Opportunities\SetSectionDealPriceData;
use App\Data\Opportunities\SubstituteAssetData;
use App\Data\Opportunities\ToggleOpportunitySurchargeGroupData;
use App\Data\PurchaseOrders\CreatePurchaseOrderData;
use App\Data\VirtualStock\ReturnVirtualStockIntakeData;
use App\Enums\DealPriceMode;
use App\Enums\OpportunityStatus;
use App\Enums\PaymentMethod;
use App\Enums\PricingRoundingDirection;
use App\Enums\VersionType;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\Models\User;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Invoices\Concerns\RecordsInvoiceAudit;
use App\Verbs\Events\Invoices\CreditNoteIssued;
use App\Verbs\Events\Invoices\PaymentRecorded;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\Events\Opportunities\DiscountReset;
use App\Verbs\Events\Opportunities\OpportunityConvertedToOrder;
use App\Verbs\Events\Opportunities\OpportunityDeleted;
use App\Verbs\Events\Opportunities\OpportunityStatusChanged;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderCancelled;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;

beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-05T10:00:00Z'));
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

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
    $invoice = batch1DraftInvoice();
    (new IssueInvoice)($invoice);
    (new RecordPayment)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => 100,
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
    $invoice = batch1DraftInvoice();
    (new IssueInvoice)($invoice);

    (new IssueCreditNote)(
        $invoice->refresh(),
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

    DB::transaction(function () use ($stateId): void {
        OpportunityDeleted::fire(opportunity_id: $stateId);
        Verbs::commit();
    });

    expect(Opportunity::query()->where('state_id', $stateId)->exists())->toBeFalse();
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
    (new ConfirmVirtualStockIntake)($intake);

    (new CancelVirtualStockIntake)($intake->refresh(), 'No longer required');

    expect($intake->fresh()->notes)->toContain('Existing note')
        ->and($intake->fresh()->notes)->toContain('Cancellation: No longer required');
});

it('returns received virtual stock intake through the returned handle path', function () {
    $intake = createTestVirtualStockIntake();
    (new ConfirmVirtualStockIntake)($intake);
    (new ReceiveVirtualStockIntake)($intake->refresh());

    (new ReturnVirtualStockIntake)(
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
    $bulkItem = $opportunity->items()->firstOrFail();

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

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '99.99',
        'deal_price_mode' => DealPriceMode::FlatHeadline->value,
    ]));

    (new SetSectionDealPrice)($opportunity->fresh(), $group->fresh(), SetSectionDealPriceData::from([
        'deal_price' => '50.00',
    ]));
    (new ClearSectionDealPrice)($opportunity->fresh(), $group->fresh());

    $altVersion = (new CreateVersion)($opportunity->fresh(), CreateVersionData::from([
        'version_type' => VersionType::Alternative->value,
    ]));
    (new ActivateVersion)($altVersion->fresh());
    $discardVersion = (new CreateVersion)($opportunity->fresh(), CreateVersionData::from([
        'version_type' => VersionType::Alternative->value,
    ]));
    (new DeleteVersion)($discardVersion->fresh());

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
    (new RevertAssetStatus)($asset->fresh());
    (new SubstituteAsset)($asset->fresh(), SubstituteAssetData::from([
        'new_stock_level_id' => $replacement->id,
    ]));

    (new DispatchBulkQuantity)($bulkItem->fresh(), BulkDispatchData::from(['quantity' => '1']));
    (new ReturnBulkQuantity)($bulkItem->fresh(), BulkReturnData::from(['quantity' => '1']));

    (new RecalculateOpportunity)($opportunity->fresh(), RecalculateOpportunityData::from([
        'reset_prices' => false,
        'reset_discounts' => true,
    ]));

    (new SetItemDiscount)($bulkItem->fresh(), SetItemDiscountData::from([
        'discount_percent' => '10.00',
    ]));

    (new SetDealPrice)($opportunity->fresh(), SetDealPriceData::from([
        'deal_total' => '99.99',
        'deal_price_mode' => DealPriceMode::FlatHeadline->value,
    ]));

    $surchargeGroup = SurchargeGroup::factory()->create();
    $surcharge = Surcharge::factory()->for($surchargeGroup, 'surchargeGroup')->percent('10.00')->create();
    (new ToggleOpportunitySurchargeGroup)($opportunity->fresh(), ToggleOpportunitySurchargeGroupData::from([
        'surcharge_group_id' => $surchargeGroup->id,
        'is_active' => true,
    ]));

    (new OverrideOpportunityItemSurcharge)($opportunity->fresh(), $bulkItem->fresh(), OverrideOpportunityItemSurchargeData::from([
        'surcharge_id' => $surcharge->id,
        'amount_minor' => 500,
    ]));

    expect($opportunity->fresh()->items()->count())->toBeGreaterThan(0);
});

it('uses invoice and opportunity audit traits on committed events', function () {
    $invoice = batch1DraftInvoice();
    (new IssueInvoice)($invoice);
    (new IssueCreditNote)($invoice->refresh(), IssueCreditNoteData::from(['lines' => null]));

    $creditEvent = latestVerbEvent(CreditNoteIssued::class)->event();
    expect(in_array(RecordsInvoiceAudit::class, class_uses_recursive($creditEvent)))->toBeTrue();

    $opportunity = batch1QuotationWithBulkItem();
    (new ConvertToOrder)($opportunity->fresh());

    $orderEvent = latestVerbEvent(OpportunityConvertedToOrder::class)->event();
    expect(in_array(RecordsOpportunityAudit::class, class_uses_recursive($orderEvent)))->toBeTrue();
});
