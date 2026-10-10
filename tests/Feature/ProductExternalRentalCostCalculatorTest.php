<?php

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use App\Services\Products\ProductExternalRentalCostCalculator;

it('compares expected sub-hire PO cost with an issued supplier bill', function (): void {
    $product = Product::factory()->create();
    $po = PurchaseOrder::factory()->sent()->create([
        'po_type' => 'sub_hire',
        'currency_code' => 'EUR',
        'subtotal' => 25000,
        'total' => 25000,
    ]);
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => '1.00',
        'unit_cost' => 25000,
        'total' => 25000,
    ]);
    $bill = SupplierBill::factory()->create([
        'supplier_id' => $po->supplier_id,
        'store_id' => $po->store_id,
        'purchase_order_id' => $po->id,
        'currency_code' => 'EUR',
        'issued_at' => now(),
    ]);
    SupplierBillItem::factory()->create([
        'supplier_bill_id' => $bill->id,
        'product_id' => $product->id,
        'purchase_order_item_id' => $poItem->id,
        'quantity' => '1.00',
        'unit_cost' => 28000,
        'total' => 28000,
    ]);

    $result = app(ProductExternalRentalCostCalculator::class)->calculate($product);

    expect($result->expectedCost)->toBe(25000)
        ->and($result->actualCost)->toBe(28000)
        ->and($result->variance)->toBe(3000)
        ->and($result->currencyCode)->toBe('EUR')
        ->and($result->actualCostComplete)->toBeTrue();
});

it('marks actual external cost incomplete when a qualifying PO has no issued bill', function (): void {
    $product = Product::factory()->create();
    $po = PurchaseOrder::factory()->sent()->create(['po_type' => 'sub_hire', 'currency_code' => 'EUR']);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => '1.00',
        'unit_cost' => 25000,
        'total' => 25000,
    ]);

    $result = app(ProductExternalRentalCostCalculator::class)->calculate($product);

    expect($result->expectedCost)->toBe(25000)
        ->and($result->actualCost)->toBeNull()
        ->and($result->actualCostComplete)->toBeFalse()
        ->and($result->variance)->toBeNull();
});

it('excludes draft and void supplier bills and does not attribute free-text lines', function (): void {
    $product = Product::factory()->create();
    $po = PurchaseOrder::factory()->sent()->create(['po_type' => 'sub_hire', 'currency_code' => 'EUR']);
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => '1.00',
        'unit_cost' => 25000,
        'total' => 25000,
    ]);
    $draft = SupplierBill::factory()->draft()->create(['purchase_order_id' => $po->id, 'currency_code' => 'EUR']);
    SupplierBillItem::factory()->create([
        'supplier_bill_id' => $draft->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => null,
        'total' => 25000,
    ]);
    $void = SupplierBill::factory()->void()->create(['purchase_order_id' => $po->id, 'currency_code' => 'EUR']);
    SupplierBillItem::factory()->create([
        'supplier_bill_id' => $void->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $product->id,
        'total' => 25000,
    ]);

    $result = app(ProductExternalRentalCostCalculator::class)->calculate($product);

    expect($result->actualCost)->toBeNull()
        ->and($result->actualCostComplete)->toBeFalse();
});

it('tracks partial billing coverage by purchase order item and only exposes final variance when complete', function (): void {
    $product = Product::factory()->create();
    $po = PurchaseOrder::factory()->sent()->create([
        'po_type' => 'sub_hire',
        'currency_code' => 'EUR',
    ]);
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => '10.00',
        'unit_cost' => 10000,
        'total' => 100000,
    ]);

    $firstBill = SupplierBill::factory()->create([
        'supplier_id' => $po->supplier_id,
        'purchase_order_id' => $po->id,
        'currency_code' => 'EUR',
    ]);
    SupplierBillItem::factory()->create([
        'supplier_bill_id' => $firstBill->id,
        'product_id' => $product->id,
        'purchase_order_item_id' => $poItem->id,
        'quantity' => '5.00',
        'unit_cost' => 10000,
        'total' => 50000,
    ]);

    $partial = app(ProductExternalRentalCostCalculator::class)->calculate($product);

    expect($partial->expectedCost)->toBe(100000)
        ->and($partial->actualCost)->toBe(50000)
        ->and($partial->actualCostComplete)->toBeFalse()
        ->and($partial->variance)->toBeNull();

    $secondBill = SupplierBill::factory()->create([
        'supplier_id' => $po->supplier_id,
        'purchase_order_id' => $po->id,
        'currency_code' => 'EUR',
    ]);
    SupplierBillItem::factory()->create([
        'supplier_bill_id' => $secondBill->id,
        'product_id' => $product->id,
        'purchase_order_item_id' => $poItem->id,
        'quantity' => '5.00',
        'unit_cost' => 11000,
        'total' => 55000,
    ]);

    $complete = app(ProductExternalRentalCostCalculator::class)->calculate($product);

    expect($complete->actualCost)->toBe(105000)
        ->and($complete->actualCostComplete)->toBeTrue()
        ->and($complete->variance)->toBe(5000);
});

it('keeps coverage identity separate for purchase order items with the same product', function (): void {
    $product = Product::factory()->create();
    $po = PurchaseOrder::factory()->sent()->create([
        'po_type' => 'sub_hire',
        'currency_code' => 'EUR',
    ]);
    $firstItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => '2.00',
        'unit_cost' => 10000,
        'total' => 20000,
    ]);
    $secondItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => '2.00',
        'unit_cost' => 10000,
        'total' => 20000,
    ]);
    $bill = SupplierBill::factory()->create([
        'supplier_id' => $po->supplier_id,
        'purchase_order_id' => $po->id,
        'currency_code' => 'EUR',
    ]);
    SupplierBillItem::factory()->create([
        'supplier_bill_id' => $bill->id,
        'product_id' => $product->id,
        'purchase_order_item_id' => $firstItem->id,
        'quantity' => '2.00',
        'unit_cost' => 10000,
        'total' => 20000,
    ]);

    $result = app(ProductExternalRentalCostCalculator::class)->calculate($product);

    expect($result->actualCost)->toBe(20000)
        ->and($result->actualCostComplete)->toBeFalse()
        ->and($result->variance)->toBeNull();
});

it('accepts billed quantity above the ordered quantity as covered while retaining actual billed cost', function (): void {
    $product = Product::factory()->create();
    $po = PurchaseOrder::factory()->sent()->create([
        'po_type' => 'sub_hire',
        'currency_code' => 'EUR',
    ]);
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => '2.00',
        'unit_cost' => 10000,
        'total' => 20000,
    ]);
    $bill = SupplierBill::factory()->create([
        'supplier_id' => $po->supplier_id,
        'purchase_order_id' => $po->id,
        'currency_code' => 'EUR',
    ]);
    SupplierBillItem::factory()->create([
        'supplier_bill_id' => $bill->id,
        'product_id' => $product->id,
        'purchase_order_item_id' => $poItem->id,
        'quantity' => '3.00',
        'unit_cost' => 10000,
        'total' => 30000,
    ]);

    $result = app(ProductExternalRentalCostCalculator::class)->calculate($product);

    expect($result->actualCost)->toBe(30000)
        ->and($result->actualCostComplete)->toBeTrue()
        ->and($result->variance)->toBe(10000);
});

it('excludes permanent purchase orders from external rental cost', function (): void {
    $product = Product::factory()->create();
    $po = PurchaseOrder::factory()->sent()->create([
        'po_type' => 'purchase',
        'currency_code' => 'EUR',
    ]);
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => '1.00',
        'unit_cost' => 25000,
        'total' => 25000,
    ]);
    $bill = SupplierBill::factory()->create([
        'supplier_id' => $po->supplier_id,
        'purchase_order_id' => $po->id,
        'currency_code' => 'EUR',
    ]);
    SupplierBillItem::factory()->create([
        'supplier_bill_id' => $bill->id,
        'product_id' => $product->id,
        'purchase_order_item_id' => $poItem->id,
        'quantity' => '1.00',
        'unit_cost' => 25000,
        'total' => 25000,
    ]);

    $result = app(ProductExternalRentalCostCalculator::class)->calculate($product);

    expect($result->expectedCost)->toBeNull()
        ->and($result->actualCost)->toBeNull()
        ->and($result->actualCostComplete)->toBeTrue();
});

it('returns unavailable monetary analytics for incompatible currencies', function (): void {
    $product = Product::factory()->create();
    $eurPo = PurchaseOrder::factory()->sent()->create([
        'po_type' => 'sub_hire',
        'currency_code' => 'EUR',
    ]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $eurPo->id,
        'product_id' => $product->id,
        'quantity' => '1.00',
        'unit_cost' => 10000,
        'total' => 10000,
    ]);
    $usdPo = PurchaseOrder::factory()->sent()->create([
        'po_type' => 'sub_hire',
        'currency_code' => 'USD',
    ]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $usdPo->id,
        'product_id' => $product->id,
        'quantity' => '1.00',
        'unit_cost' => 10000,
        'total' => 10000,
    ]);

    $result = app(ProductExternalRentalCostCalculator::class)->calculate($product);

    expect($result->expectedCost)->toBeNull()
        ->and($result->actualCost)->toBeNull()
        ->and($result->variance)->toBeNull()
        ->and($result->financialsComplete)->toBeFalse();
});

it('does not mark a product-backed bill complete without purchase order item coverage', function (): void {
    $product = Product::factory()->create();
    $po = PurchaseOrder::factory()->sent()->create([
        'po_type' => 'sub_hire',
        'currency_code' => 'EUR',
    ]);
    $bill = SupplierBill::factory()->create([
        'supplier_id' => $po->supplier_id,
        'purchase_order_id' => $po->id,
        'currency_code' => 'EUR',
    ]);
    SupplierBillItem::factory()->create([
        'supplier_bill_id' => $bill->id,
        'product_id' => $product->id,
        'quantity' => '1.00',
        'unit_cost' => 25000,
        'total' => 25000,
    ]);

    $result = app(ProductExternalRentalCostCalculator::class)->calculate($product);

    expect($result->actualCost)->toBe(25000)
        ->and($result->actualCostComplete)->toBeFalse()
        ->and($result->variance)->toBeNull();
});
