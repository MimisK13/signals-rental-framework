<?php

namespace Database\Factories;

use App\Enums\SupplierBillStatus;
use App\Models\Member;
use App\Models\Store;
use App\Models\SupplierBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierBill>
 */
class SupplierBillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Member::factory()->organisation(),
            'store_id' => Store::factory(),
            'document_number' => 'SB-'.$this->faker->unique()->numerify('######'),
            'status' => SupplierBillStatus::Issued,
            'issued_at' => now()->subDay(),
            'subtotal' => 10000,
            'tax_total' => 0,
            'total' => 10000,
            'currency_code' => 'EUR',
            'exchange_rate' => '1',
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => SupplierBillStatus::Draft, 'issued_at' => null]);
    }

    public function void(): static
    {
        return $this->state(['status' => SupplierBillStatus::Void]);
    }
}
