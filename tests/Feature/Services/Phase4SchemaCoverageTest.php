<?php

use App\Contracts\HasSchema;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\ProductSupplierCost;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\VirtualStockIntake;
use App\Services\SchemaRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * P56 — Phase-4 field-registry coverage for billing, sub-hire, and containers.
 *
 * @var array<class-string, array<int, string>> $phase4Models
 */
$phase4Models = [
    Invoice::class => ['id', 'state_id', 'is_posting', 'overdue_notified_at', 'exchange_rate', 'prices_include_tax', 'notes', 'reference', 'tag_list'],
    InvoiceItem::class => ['id', 'opportunity_item_id', 'product_tax_class_id', 'exchange_rate', 'prices_include_tax', 'sort_order'],
    InvoicePayment::class => ['id', 'notes', 'created_by'],
    CreditNote::class => ['id', 'exchange_rate', 'prices_include_tax', 'notes'],
    PurchaseOrder::class => ['id', 'state_id', 'shortage_resolution_id', 'rounding_adjustment', 'exchange_rate', 'created_by', 'reference', 'notes', 'supplier_reference', 'cancelled_at'],
    PurchaseOrderItem::class => ['id', 'product_id', 'supplier_cost_id', 'received_quantity', 'product_tax_class_id', 'sort_order'],
    VirtualStockIntake::class => ['id', 'state_id', 'purchase_order_item_id', 'cost_apportionment_strategy', 'overrun_notified_at', 'cancelled_at', 'notes', 'created_by', 'demand_id'],
    ProductSupplierCost::class => ['supplier_sku', 'lead_time_days', 'min_order_quantity', 'metadata'],
];

describe('Phase 4 field-registry coverage', function () use ($phase4Models) {
    it('declares every Phase-4 model as HasSchema', function (string $modelClass) {
        expect(is_subclass_of($modelClass, HasSchema::class))
            ->toBeTrue("{$modelClass} must implement HasSchema so it appears in /api/v1/schema/{model}");
    })->with(array_keys($phase4Models));

    it('covers the editable surface in the resolved schema', function (string $modelClass, array $excluded) {
        /** @var Model $instance */
        $instance = new $modelClass;

        $fillable = $instance->getFillable();
        expect($fillable)->not->toBeEmpty("{$modelClass} should declare a fillable surface");

        $schema = (new SchemaRegistry)->resolve($modelClass);
        $schemaKeys = array_keys($schema);

        $expected = array_values(array_diff($fillable, $excluded));
        $missing = array_values(array_diff($expected, $schemaKeys));

        expect($missing)->toBe(
            [],
            sprintf(
                '%s exposes %s via $fillable but does not register %s in defineSchema(); '
                .'they would be absent from /api/v1/schema/%s.',
                class_basename($modelClass),
                implode(', ', $expected),
                implode(', ', $missing),
                Str::plural(Str::snake(class_basename($modelClass))),
            ),
        );
    })->with(array_map(
        fn (string $modelClass, array $excluded): array => [$modelClass, $excluded],
        array_keys($phase4Models),
        array_values($phase4Models),
    ));

    it('registers money columns with currency field types', function (string $modelClass, string $moneyField) {
        $schema = (new SchemaRegistry)->resolve($modelClass);

        expect($schema[$moneyField]->type)->toBe('currency');
    })->with([
        [Invoice::class, 'total'],
        [PurchaseOrder::class, 'total'],
        [VirtualStockIntake::class, 'unit_cost'],
        [ProductSupplierCost::class, 'cost'],
    ]);
});
