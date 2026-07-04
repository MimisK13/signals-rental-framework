<?php

namespace App\Actions\PurchaseOrders\Concerns;

use App\Services\PurchaseOrders\PurchaseOrderSupplierCostResolver;
use App\Services\SequenceAllocator;
use Illuminate\Validation\ValidationException;

trait BuildsPurchaseOrderLinePayloads
{
    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    protected function buildLinePayloads(
        array $lines,
        int $supplierId,
        int $storeId,
        ?SequenceAllocator $existingAllocator = null,
    ): array {
        $allocator = $existingAllocator ?? app(SequenceAllocator::class);
        $resolver = app(PurchaseOrderSupplierCostResolver::class);
        $prepared = [];

        foreach ($lines as $line) {
            $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;
            $unitCost = array_key_exists('unit_cost', $line) && $line['unit_cost'] !== null
                ? (int) $line['unit_cost']
                : null;
            $supplierCostId = isset($line['supplier_cost_id'])
                ? (int) $line['supplier_cost_id']
                : null;

            if ($productId !== null && $unitCost === null) {
                $supplierCost = $resolver->resolve($productId, $supplierId, $storeId);

                if ($supplierCost !== null) {
                    $unitCost = (int) $supplierCost->cost;
                    $supplierCostId = (int) $supplierCost->id;
                }
            }

            if ($unitCost === null) {
                throw ValidationException::withMessages([
                    'lines' => ['Each line requires a unit cost when no supplier cost can be resolved.'],
                ]);
            }

            $prepared[] = [
                'quantity' => bcadd((string) ($line['quantity'] ?? '1'), '0', 2),
                'unit_cost' => $unitCost,
                'tax' => (int) ($line['tax'] ?? 0),
                'product_id' => $productId,
                'description' => (string) ($line['description'] ?? ''),
                'product_tax_class_id' => isset($line['product_tax_class_id']) ? (int) $line['product_tax_class_id'] : null,
                'supplier_cost_id' => $supplierCostId,
                'purchase_order_item_id' => isset($line['id'])
                    ? (int) $line['id']
                    : $allocator->next('purchase_order_items'),
            ];
        }

        return $prepared;
    }
}
