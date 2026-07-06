<?php

namespace App\Data\SupplierCosts;

use App\Data\Concerns\EntityReferenceData;
use App\Data\Concerns\FormatsTimestamps;
use App\Models\ProductSupplierCost;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class ProductSupplierCostData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public int $product_id,
        public int $supplier_id,
        public ?int $store_id,
        public string $cost,
        public string $currency_code,
        public string $cost_basis,
        public string $cost_basis_label,
        public ?string $supplier_sku,
        public ?int $lead_time_days,
        public string $min_order_quantity,
        public bool $is_preferred,
        /** @var array<string, mixed>|null */
        public ?array $metadata,
        public string $created_at,
        public string $updated_at,
        public ?EntityReferenceData $supplier = null,
        public ?EntityReferenceData $store = null,
    ) {}

    public static function fromModel(ProductSupplierCost $cost): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $cost->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $cost->updated_at;

        $basisLabel = config("supplier_costs.cost_bases.{$cost->cost_basis}.label", $cost->cost_basis);

        $metadata = $cost->metadata;

        return new self(
            id: $cost->id,
            product_id: $cost->product_id,
            supplier_id: $cost->supplier_id,
            store_id: $cost->store_id,
            cost: $cost->formatMoneyCost('cost'),
            currency_code: $cost->currency_code,
            cost_basis: $cost->cost_basis,
            cost_basis_label: is_string($basisLabel) ? $basisLabel : $cost->cost_basis,
            supplier_sku: $cost->supplier_sku,
            lead_time_days: $cost->lead_time_days,
            min_order_quantity: bcadd((string) $cost->min_order_quantity, '0', 2),
            is_preferred: $cost->is_preferred,
            metadata: is_array($metadata) ? $metadata : null,
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
            supplier: $cost->relationLoaded('supplier') && $cost->supplier
                ? EntityReferenceData::from(['id' => $cost->supplier->id, 'name' => $cost->supplier->name])
                : null,
            store: $cost->relationLoaded('store') && $cost->store
                ? EntityReferenceData::from(['id' => $cost->store->id, 'name' => $cost->store->name])
                : null,
        );
    }
}
