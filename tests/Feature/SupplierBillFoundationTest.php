<?php

use App\Actions\SupplierBills\CreateSupplierBill;
use App\Actions\SupplierBills\IssueSupplierBill;
use App\Actions\SupplierBills\VoidSupplierBill;
use App\Enums\SupplierBillStatus;
use App\Models\Member;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $user = User::factory()->owner()->create();
    $user->givePermissionTo([
        'costs.view', 'supplier_bills.view', 'supplier_bills.create', 'supplier_bills.edit',
        'supplier_bills.issue', 'supplier_bills.void',
    ]);
    $this->actingAs($user);
});

it('creates and totals a draft supplier bill using integer minor units', function (): void {
    $supplier = Member::factory()->organisation()->create();
    $store = Store::factory()->create();
    $product = Product::factory()->create();

    $bill = app(CreateSupplierBill::class)([
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'document_number' => 'SUP-100',
        'currency_code' => 'EUR',
    ], [[
        'product_id' => $product->id,
        'description' => 'M32 sub-hire',
        'quantity' => '2.00',
        'unit_cost' => 12500,
        'tax' => 0,
    ]]);

    expect($bill->status)->toBe(SupplierBillStatus::Draft)
        ->and($bill->subtotal)->toBe(25000)
        ->and($bill->total)->toBe(25000)
        ->and($bill->items)->toHaveCount(1);
});

it('issues and voids supplier bills without treating either as payment', function (): void {
    $bill = SupplierBill::factory()->draft()->create();

    (new IssueSupplierBill)($bill);
    expect($bill->refresh()->status)->toBe(SupplierBillStatus::Issued)
        ->and($bill->issued_at)->not->toBeNull();

    (new VoidSupplierBill)($bill);
    expect($bill->refresh()->status)->toBe(SupplierBillStatus::Void);
});

it('keeps structured procurement relationships on bill items', function (): void {
    $product = Product::factory()->create();
    $po = PurchaseOrder::factory()->create(['po_type' => 'sub_hire']);
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
    ]);
    $bill = SupplierBill::factory()->create([
        'supplier_id' => $po->supplier_id,
        'store_id' => $po->store_id,
        'purchase_order_id' => $po->id,
    ]);
    $item = SupplierBillItem::factory()->create([
        'supplier_bill_id' => $bill->id,
        'product_id' => $product->id,
        'purchase_order_item_id' => $poItem->id,
    ]);

    expect($item->supplierBill->is($bill))->toBeTrue()
        ->and($item->purchaseOrderItem->is($poItem))->toBeTrue()
        ->and($bill->purchaseOrder->is($po))->toBeTrue();
});
