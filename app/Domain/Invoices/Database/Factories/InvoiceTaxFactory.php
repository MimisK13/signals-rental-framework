<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Database\Factories;

use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Models\InvoiceTax;
use App\Models\TaxRate;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceTax>
 */
class InvoiceTaxFactory extends Factory
{
    protected $model = InvoiceTax::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $taxable = fake()->numberBetween(10000, 100000);
        $tax = (int) round($taxable * 0.2);

        return [
            'id' => app(SequenceAllocator::class)->next('invoice_taxes'),
            'invoice_id' => Invoice::factory(),
            'tax_rate_id' => TaxRate::factory(),
            'taxable_amount' => $taxable,
            'tax_amount' => $tax,
            'created_at' => now(),
        ];
    }
}
