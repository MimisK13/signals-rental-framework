<?php

namespace Database\Factories;

use App\Enums\TaxResultType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(1000, 50000);
        $quantity = fake()->randomFloat(2, 1, 5);
        $total = (int) round($unitPrice * $quantity);

        return [
            'id' => app(SequenceAllocator::class)->next('invoice_items'),
            'invoice_id' => Invoice::factory(),
            'description' => fake()->sentence(4),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'tax_treatment' => TaxResultType::Standard->value,
            'currency_code' => 'GBP',
            'exchange_rate' => '1',
            'prices_include_tax' => false,
            'sort_order' => 0,
        ];
    }
}
