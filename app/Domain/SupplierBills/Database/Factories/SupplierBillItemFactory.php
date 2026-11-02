<?php

declare(strict_types=1);

namespace App\Domain\SupplierBills\Database\Factories;

use App\Domain\SupplierBills\Models\SupplierBill;
use App\Domain\SupplierBills\Models\SupplierBillItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierBillItem>
 */
class SupplierBillItemFactory extends Factory
{
    protected $model = SupplierBillItem::class;

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
