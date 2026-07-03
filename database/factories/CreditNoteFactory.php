<?php

namespace Database\Factories;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => app(SequenceAllocator::class)->next('credit_notes'),
            'invoice_id' => Invoice::factory(),
            'number' => 'CN-'.fake()->unique()->numerify('####'),
            'status' => 'issued',
            'subtotal' => -1000,
            'tax_total' => -200,
            'total' => -1200,
            'currency_code' => 'GBP',
            'exchange_rate' => '1',
            'prices_include_tax' => false,
            'issued_at' => now(),
        ];
    }
}
