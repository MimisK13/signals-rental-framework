<?php

use App\Actions\PurchaseOrders\CancelPurchaseOrder;
use App\Actions\PurchaseOrders\CreatePurchaseOrder;
use App\Actions\PurchaseOrders\DeleteDraftPurchaseOrder;
use App\Actions\PurchaseOrders\SendPurchaseOrder;
use App\Actions\PurchaseOrders\UpdateDraftPurchaseOrder;
use App\Data\PurchaseOrders\CreatePurchaseOrderData;
use App\Data\PurchaseOrders\UpdateDraftPurchaseOrderData;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a draft purchase order without lines', function () {
    $po = createTestPurchaseOrder();

    expect($po->status)->toBe('draft')
        ->and($po->total)->toBe(0)
        ->and($po->items)->toHaveCount(0);
});

it('pre-fills unit cost from preferred per-unit supplier cost', function () {
    $store = Store::factory()->create();
    $supplier = Member::factory()->organisation()->create();
    $product = Product::factory()->create();

    ProductSupplierCost::factory()->create([
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'cost' => 2500,
        'cost_basis' => 'per_unit',
        'is_preferred' => true,
    ]);

    ProductSupplierCost::factory()->create([
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'store_id' => null,
        'cost' => 9999,
        'cost_basis' => 'per_unit',
        'is_preferred' => false,
    ]);

    $result = (new CreatePurchaseOrder)(CreatePurchaseOrderData::from([
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'lines' => [[
            'product_id' => $product->id,
            'description' => 'Widget',
            'quantity' => '2.00',
        ]],
    ]));

    $item = PurchaseOrder::query()->findOrFail($result->id)->items()->first();

    expect($item)->not->toBeNull()
        ->and($item->unit_cost)->toBe(2500)
        ->and($item->total)->toBe(5000)
        ->and($item->supplier_cost_id)->not->toBeNull();
});

it('updates draft purchase orders only', function () {
    $po = createTestPurchaseOrder();

    (new UpdateDraftPurchaseOrder)($po, UpdateDraftPurchaseOrderData::from([
        'supplier_id' => $po->supplier_id,
        'store_id' => $po->store_id,
        'lines' => [[
            'description' => 'Updated line',
            'quantity' => '1.00',
            'unit_cost' => 1000,
        ]],
    ]));

    expect($po->refresh()->items)->toHaveCount(1)
        ->and($po->items->first()->description)->toBe('Updated line');

    $sent = createTestPurchaseOrder();
    (new SendPurchaseOrder)($sent);

    expect(fn () => (new UpdateDraftPurchaseOrder)($sent->refresh(), UpdateDraftPurchaseOrderData::from([
        'supplier_id' => $sent->supplier_id,
        'store_id' => $sent->store_id,
        'lines' => [],
    ])))->toThrow(ValidationException::class);
});

it('sends a draft purchase order', function () {
    $po = createTestPurchaseOrder();

    (new SendPurchaseOrder)($po);

    $po->refresh();

    expect($po->status)->toBe('sent')
        ->and($po->sent_at)->not->toBeNull();
});

it('rejects cancelling received or partially received purchase orders', function () {
    $received = PurchaseOrder::factory()->received()->create();
    $partial = PurchaseOrder::factory()->partiallyReceived()->create();

    expect(fn () => (new CancelPurchaseOrder)($received, 'Too late'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => (new CancelPurchaseOrder)($partial, 'Too late'))
        ->toThrow(InvalidArgumentException::class);
});

it('cancels a sent purchase order', function () {
    $po = createTestPurchaseOrder();
    (new SendPurchaseOrder)($po);

    (new CancelPurchaseOrder)($po->refresh(), 'Supplier unavailable');

    expect($po->refresh()->status)->toBe('cancelled')
        ->and($po->cancelled_at)->not->toBeNull();
});

it('deletes draft purchase orders via event sourcing', function () {
    $po = createTestPurchaseOrder();

    (new DeleteDraftPurchaseOrder)($po);

    expect(PurchaseOrder::query()->find($po->id))->toBeNull()
        ->and(PurchaseOrder::withTrashed()->find($po->id)?->deleted_at)->not->toBeNull();
});

it('rejects non-organisation suppliers', function () {
    $store = Store::factory()->create();
    $contact = Member::factory()->contact()->create();

    expect(fn () => CreatePurchaseOrderData::validateAndCreate([
        'supplier_id' => $contact->id,
        'store_id' => $store->id,
        'po_type' => 'purchase',
    ]))->toThrow(ValidationException::class);
});
