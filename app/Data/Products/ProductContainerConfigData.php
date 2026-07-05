<?php

namespace App\Data\Products;

use App\Models\Product;
use Spatie\LaravelData\Data;

class ProductContainerConfigData extends Data
{
    /**
     * @param  array{slots?: list<array{product_id: int, quantity: int, binding?: string|null}>, max_items?: int|null}|null  $container_template
     */
    public function __construct(
        public bool $is_containerable,
        public string $container_availability_mode,
        public ?string $container_checkin_mode,
        public int $container_max_nesting_depth,
        public bool $container_repack_on_return,
        public bool $is_warehouse_addable,
        public ?int $warehouse_add_default_charge,
        public ?array $container_template,
    ) {}

    public static function fromModel(Product $product): self
    {
        $mode = $product->containerMode();

        return new self(
            is_containerable: $product->isContainerable(),
            container_availability_mode: $mode->value,
            container_checkin_mode: $product->container_checkin_mode?->value,
            container_max_nesting_depth: $product->container_max_nesting_depth,
            container_repack_on_return: (bool) $product->container_repack_on_return,
            is_warehouse_addable: (bool) $product->is_warehouse_addable,
            warehouse_add_default_charge: $product->warehouse_add_default_charge,
            container_template: $product->container_template,
        );
    }
}
