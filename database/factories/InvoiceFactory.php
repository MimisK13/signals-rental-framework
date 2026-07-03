<?php

namespace Database\Factories;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Store;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => app(SequenceAllocator::class)->next('invoices'),
            'state_id' => snowflake_id(),
            'number' => 'INV-'.fake()->unique()->numerify('####'),
            'type' => InvoiceType::Standard->value,
            'status' => 'draft',
            'member_id' => Member::factory()->organisation(),
            'store_id' => Store::factory(),
            'currency_code' => 'GBP',
            'exchange_rate' => '1',
            'prices_include_tax' => false,
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'amount_paid' => 0,
            'amount_outstanding' => 0,
            'tag_list' => [],
        ];
    }

    public function deposit(): static
    {
        return $this->state(fn (): array => ['type' => InvoiceType::Deposit->value]);
    }

    public function proforma(): static
    {
        return $this->state(fn (): array => ['type' => InvoiceType::Proforma->value]);
    }

    public function issued(): static
    {
        return $this->state(fn (): array => [
            'status' => 'issued',
            'issued_at' => now(),
        ]);
    }
}
