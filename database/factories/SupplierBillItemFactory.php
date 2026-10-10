<?php

namespace Database\Factories;

use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierBillItem>
 */
class SupplierBillItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_bill_id' => SupplierBill::factory(),
            'description' => $this->faker->words(3, true),
            'quantity' => '1.00',
            'unit_cost' => 10000,
            'tax' => 0,
            'total' => 10000,
        ];
    }
}
