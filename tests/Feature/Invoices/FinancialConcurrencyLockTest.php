<?php

use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\ReceivePurchaseOrder;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Concerns\LocksModelsForMutation;
use App\Data\PurchaseOrders\CreatePurchaseOrderData;
use App\Data\PurchaseOrders\ReceivePurchaseOrderData;
use App\Models\Member;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\TaxZone;
use App\Models\TaxZoneMember;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxZoneRuleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('rejects locked-model assertion when status changed inside the transaction', function () {
    $store = Store::factory()->create();
    $supplier = Member::factory()->organisation()->create();

    $created = (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'po_type' => 'purchase',
        'lines' => [[
            'description' => 'Widget',
            'quantity' => '1.00',
            'unit_cost' => 1000,
        ]],
    ]));

    $purchaseOrder = PurchaseOrder::query()->findOrFail($created->id);
    (new SendPurchaseOrder)($purchaseOrder);

    $helper = new class
    {
        use LocksModelsForMutation;

        public function assertSent(PurchaseOrder $purchaseOrder): void
        {
            $this->lockModelForMutation(
                $purchaseOrder,
                ['sent'],
                failureKey: 'purchase_order',
            );
        }
    };

    DB::transaction(function () use ($purchaseOrder, $helper): void {
        PurchaseOrder::query()
            ->whereKey($purchaseOrder->id)
            ->update(['status' => 'cancelled']);

        expect(fn () => $helper->assertSent($purchaseOrder->refresh()))
            ->toThrow(ValidationException::class, 'Cannot proceed');
    });
});

it('rolls back purchase order receipt when stock write fails', function () {
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

    $store = Store::factory()->create(['tax_zone_id' => $uk->id, 'country_code' => 'GB']);
    $supplier = Member::factory()->organisation()->create([
        'purchase_tax_class_id' => $orgClass->id,
        'tax_zone_id' => $uk->id,
    ]);
    $product = Product::factory()->bulk()->create([
        'purchase_tax_class_id' => $productClass->id,
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

    $stockLevel->delete();

    $eventsBefore = verbEventCount();

    expect(fn () => app(ReceivePurchaseOrder::class)($po->refresh(), ReceivePurchaseOrderData::from([
        'lines' => [[
            'purchase_order_item_id' => $item->id,
            'quantity' => '2.00',
        ]],
    ])))->toThrow(ValidationException::class, 'stock level');

    $po->refresh();

    expect($po->status)->toBe('sent')
        ->and((string) $po->items()->firstOrFail()->received_quantity)->toBe('0.00')
        ->and(verbEventCount())->toBe($eventsBefore)
        ->and(StockLevel::query()->where('product_id', $product->id)->where('store_id', $store->id)->exists())->toBeFalse();
});
