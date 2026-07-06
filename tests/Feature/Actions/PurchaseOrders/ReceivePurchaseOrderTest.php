<?php

use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\ReceivePurchaseOrder;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\PurchaseOrders\CreatePurchaseOrderData;
use App\Data\PurchaseOrders\ReceivePurchaseOrderData;
use App\Enums\AvailabilityResolution;
use App\Enums\ShortageResolutionStatus;
use App\Enums\StockMethod;
use App\Enums\TransactionType;
use App\Models\Demand;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\ProductTaxClass;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\TaxZone;
use App\Models\TaxZoneMember;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\AvailabilityService;
use App\Services\Shortages\Resolvers\SubHireResolver;
use App\Services\Shortages\ShortageDetector;
use App\ValueObjects\Shortage;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxZoneRuleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    config([
        'signals.installed' => true,
        'signals.setup_complete' => true,
        'shortages.resolvers.subhire.auto_executable' => false,
    ]);

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-04T12:00:00Z'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @return array<string, mixed>
 */
function receivePoTaxInfrastructure(): array
{
    $uk = TaxZone::factory()->uk()->create();
    TaxZoneMember::factory()->create(['tax_zone_id' => $uk->id, 'country_code' => 'GB']);

    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['name' => 'Standard VAT', 'rate' => '20.0000', 'is_active' => true]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    (new TaxZoneRuleSeeder)->run();

    return compact('uk', 'orgClass', 'productClass');
}

/**
 * @param  list<string>  $permissions
 */
function receivePoApiUserForStore(int $storeId, array $permissions): User
{
    $member = Member::factory()->create();
    $user = User::factory()->create([
        'member_id' => $member->id,
        'is_owner' => false,
        'is_active' => true,
    ]);
    $user->givePermissionTo($permissions);

    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $storeId,
        'created_at' => now(),
    ]);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function receiveSubHireScenario(): array
{
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    $supplier = Member::factory()->organisation()->create(['name' => 'Stage Hire Ltd']);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 1,
    ]);

    $opportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-05T17:00:00Z'),
    ]);

    OpportunityItem::factory()->for($opportunity)->create([
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => '3',
    ]);

    ProductSupplierCost::factory()->create([
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'cost' => 5000,
        'cost_basis' => 'per_unit',
    ]);

    $shortage = Shortage::make(
        opportunityItemId: $opportunity->items()->first()->id,
        opportunityId: $opportunity->id,
        productId: $product->id,
        productName: $product->name,
        storeId: $store->id,
        requestedQuantity: 3,
        availableQuantity: 1,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-05T17:00:00Z'),
        isCritical: false,
    );

    $option = app(SubHireResolver::class)->getOptions($shortage)[0];
    $result = app(SubHireResolver::class)->apply($shortage, $option);

    $resolution = $result->resolution->fresh();
    $po = PurchaseOrder::query()->findOrFail((int) $resolution->metadata['purchase_order_id']);

    (new SendPurchaseOrder)($po);

    return compact('store', 'product', 'supplier', 'opportunity', 'resolution', 'po');
}

it('receives a sub-hire po fully confirming intake fulfilling resolution and clearing shortage', function () {
    ['store' => $store, 'product' => $product, 'opportunity' => $opportunity, 'resolution' => $resolution, 'po' => $po] = receiveSubHireScenario();

    $item = $po->items()->firstOrFail();
    $availability = app(AvailabilityService::class);

    $before = $availability->availableForItem(
        $product->id,
        $store->id,
        Carbon::parse('2026-07-02T12:00:00Z'),
        Carbon::parse('2026-07-04T12:00:00Z'),
        'opportunity_item',
        $opportunity->items()->first()->id,
    );

    expect($before)->toBe(1)
        ->and(app(ShortageDetector::class)->forOpportunity($opportunity->fresh()))->not->toBeEmpty();

    app(ReceivePurchaseOrder::class)($po, ReceivePurchaseOrderData::from([
        'lines' => [[
            'purchase_order_item_id' => $item->id,
            'quantity' => (string) $item->quantity,
        ]],
    ]));

    $po->refresh();
    $intake = VirtualStockIntake::query()->find($resolution->virtual_stock_intake_id);
    $demand = Demand::query()
        ->where('source_type', 'virtual_stock_intake')
        ->where('source_id', $intake?->id)
        ->first();

    expect($po->status)->toBe('received')
        ->and($po->received_at)->not->toBeNull()
        ->and($item->refresh()->received_quantity)->toBe($item->quantity)
        ->and($intake?->status)->toBe('confirmed')
        ->and($intake?->demand_id)->not->toBeNull()
        ->and($demand)->not->toBeNull()
        ->and($demand->quantity)->toBeLessThan(0)
        ->and($resolution->refresh()->status)->toBe(ShortageResolutionStatus::Fulfilled);

    $after = $availability->availableForItem(
        $product->id,
        $store->id,
        Carbon::parse('2026-07-01T09:00:00Z'),
        Carbon::parse('2026-07-05T17:00:00Z'),
        'opportunity_item',
        $opportunity->items()->first()->id,
    );

    expect($demand->is_active)->toBeTrue()
        ->and($intake->quantity)->toBe(2)
        ->and($demand->quantity)->toBe(-2)
        ->and($after)->toBe(3)
        ->and(app(ShortageDetector::class)->forOpportunity($opportunity->fresh()))->toBeEmpty();
});

it('supports partial receipt with proportional intake confirmation', function () {
    ['po' => $po, 'resolution' => $resolution] = receiveSubHireScenario();
    $item = $po->items()->firstOrFail();

    app(ReceivePurchaseOrder::class)($po, ReceivePurchaseOrderData::from([
        'lines' => [[
            'purchase_order_item_id' => $item->id,
            'quantity' => '1.00',
        ]],
    ]));

    $po->refresh();
    $intake = VirtualStockIntake::query()->find($resolution->virtual_stock_intake_id);

    expect($po->status)->toBe('partially_received')
        ->and($item->refresh()->received_quantity)->toBe('1.00')
        ->and($intake?->status)->toBe('confirmed')
        ->and($intake?->quantity)->toBe(1)
        ->and($resolution->refresh()->status)->toBe(ShortageResolutionStatus::PartiallyFulfilled);
});

it('rejects over-receipt via bc math validation', function () {
    ['po' => $po] = receiveSubHireScenario();
    $item = $po->items()->firstOrFail();

    expect(fn () => app(ReceivePurchaseOrder::class)($po, ReceivePurchaseOrderData::from([
        'lines' => [[
            'purchase_order_item_id' => $item->id,
            'quantity' => bcadd((string) $item->quantity, '1', 2),
        ]],
    ])))->toThrow(ValidationException::class);
});

it('writes a buy stock transaction for purchase-type receipts', function () {
    $tax = receivePoTaxInfrastructure();
    $store = Store::factory()->create(['tax_zone_id' => $tax['uk']->id, 'country_code' => 'GB']);
    $supplier = Member::factory()->organisation()->create([
        'purchase_tax_class_id' => $tax['orgClass']->id,
        'tax_zone_id' => $tax['uk']->id,
    ]);
    $product = Product::factory()->bulk()->create([
        'purchase_tax_class_id' => $tax['productClass']->id,
    ]);

    $stockLevel = StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 4,
    ]);

    $created = (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'po_type' => 'purchase',
        'lines' => [[
            'product_id' => $product->id,
            'description' => 'Consumable',
            'quantity' => '2.00',
            'unit_cost' => 1000,
        ]],
    ]));

    $po = PurchaseOrder::query()->findOrFail($created->id);
    (new SendPurchaseOrder)($po);
    $item = $po->items()->firstOrFail();

    app(ReceivePurchaseOrder::class)($po, ReceivePurchaseOrderData::from([
        'lines' => [[
            'purchase_order_item_id' => $item->id,
            'quantity' => '2.00',
        ]],
    ]));

    $transaction = StockTransaction::query()->latest('id')->first();

    expect($transaction?->transaction_type)->toBe(TransactionType::Buy)
        ->and((float) $transaction?->quantity)->toBe(2.0)
        ->and((float) $stockLevel->refresh()->quantity_held)->toBe(6.0);
});

it('computes expected tax at creation and actual tax with penny-exact rounding at full receipt', function () {
    $tax = receivePoTaxInfrastructure();
    $store = Store::factory()->create(['tax_zone_id' => $tax['uk']->id, 'country_code' => 'GB']);
    $supplier = Member::factory()->organisation()->create([
        'purchase_tax_class_id' => $tax['orgClass']->id,
        'tax_zone_id' => $tax['uk']->id,
    ]);
    $product = Product::factory()->bulk()->create([
        'purchase_tax_class_id' => $tax['productClass']->id,
    ]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 0,
    ]);

    $created = (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'po_type' => 'purchase',
        'lines' => [[
            'product_id' => $product->id,
            'description' => 'Taxed widget',
            'quantity' => '3.00',
            'unit_cost' => 1000,
        ]],
    ]));

    $po = PurchaseOrder::query()->with('items')->findOrFail($created->id);

    expect($po->items->first()->tax)->toBe(600)
        ->and($po->tax_total)->toBe(600);

    (new SendPurchaseOrder)($po);
    $item = $po->items()->firstOrFail();

    app(ReceivePurchaseOrder::class)($po, ReceivePurchaseOrderData::from([
        'lines' => [[
            'purchase_order_item_id' => $item->id,
            'quantity' => '3.00',
        ]],
    ]));

    $po->refresh();
    $item->refresh();
    $lineTotalSum = (int) $item->total;

    expect($po->status)->toBe('received')
        ->and($po->tax_total)->toBe(600)
        ->and($po->rounding_adjustment)->toBe(0)
        ->and($po->subtotal + $po->tax_total + $po->rounding_adjustment)->toBe($po->total)
        ->and($lineTotalSum + $po->rounding_adjustment)->toBe($po->total);
});

it('reconstructs purchase order intake and demand projections after receipt replay', function () {
    ['po' => $po, 'resolution' => $resolution] = receiveSubHireScenario();
    $item = $po->items()->firstOrFail();

    app(ReceivePurchaseOrder::class)($po, ReceivePurchaseOrderData::from([
        'lines' => [[
            'purchase_order_item_id' => $item->id,
            'quantity' => (string) $item->quantity,
        ]],
    ]));

    $ordersBefore = PurchaseOrder::withTrashed()->orderBy('id')->get()->map(
        fn (PurchaseOrder $row): array => $row->getAttributes(),
    )->all();

    $itemsBefore = PurchaseOrderItem::query()->orderBy('id')->get()->map(
        fn (PurchaseOrderItem $row): array => $row->getAttributes(),
    )->all();

    $intakesBefore = VirtualStockIntake::withTrashed()->orderBy('id')->get()->map(
        fn (VirtualStockIntake $row): array => $row->getAttributes(),
    )->all();

    $demandsBefore = Demand::query()->orderBy('id')->get()->map(
        fn (Demand $row): array => $row->getAttributes(),
    )->all();

    $eventCountBeforeReplay = verbEventCount();

    Demand::query()->delete();
    VirtualStockIntake::withTrashed()->forceDelete();
    PurchaseOrderItem::query()->delete();
    PurchaseOrder::withTrashed()->forceDelete();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay)
        ->and(PurchaseOrder::withTrashed()->orderBy('id')->get()->map(fn ($r) => $r->getAttributes())->all())->toBe($ordersBefore)
        ->and(PurchaseOrderItem::query()->orderBy('id')->get()->map(fn ($r) => $r->getAttributes())->all())->toBe($itemsBefore)
        ->and(VirtualStockIntake::withTrashed()->orderBy('id')->get()->map(fn ($r) => $r->getAttributes())->all())->toBe($intakesBefore)
        ->and(Demand::query()->orderBy('id')->get()->map(fn ($r) => $r->getAttributes())->all())->toBe($demandsBefore);
});

it('exposes receive on the api with auth validation', function () {
    ['po' => $po] = receiveSubHireScenario();
    $item = $po->items()->firstOrFail();
    $owner = User::factory()->owner()->create();
    $token = $owner->createToken('test', ['purchase_orders:read', 'purchase_orders:write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/purchase_orders/{$po->id}/receive", [
            'lines' => [[
                'purchase_order_item_id' => $item->id,
                'quantity' => (string) $item->quantity,
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('purchase_order.status', 'received');
});

it('returns 403 on receive without permission', function () {
    ['po' => $po, 'store' => $store] = receiveSubHireScenario();
    $user = receivePoApiUserForStore($store->id, [
        'costs.view',
        'purchase_orders.access',
        'purchase_orders.view',
    ]);
    $token = $user->createToken('test', ['purchase_orders:write'])->plainTextToken;

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/purchase_orders/{$po->id}/receive", [
            'lines' => [[
                'purchase_order_item_id' => $po->items()->first()->id,
                'quantity' => '1.00',
            ]],
        ])
        ->assertForbidden();
});

it('smokes the receive modal on the purchase order show page', function () {
    ['po' => $po] = receiveSubHireScenario();
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['costs.view', 'purchase_orders.access', 'purchase_orders.view', 'purchase_orders.receive']);
    $this->actingAs($user);

    Volt::test('purchase-orders.show', ['purchaseOrder' => $po])
        ->assertSee('Receive')
        ->call('openReceiveModal')
        ->assertSet('showReceiveModal', true)
        ->call('receive')
        ->assertSet('showReceiveModal', false);

    expect($po->refresh()->status)->toBe('received');
});
