<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => app(SequenceAllocator::class)->next('purchase_order_items'),
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'description' => fake()->words(3, true),
            'quantity' => '1.00',
            'received_quantity' => '0.00',
            'unit_cost' => 1000,
            'tax' => 0,
            'total' => 1000,
            'sort_order' => 0,
        ];
    }
}
