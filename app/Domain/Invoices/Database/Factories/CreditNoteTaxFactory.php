<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Database\Factories;

use App\Domain\Invoices\Models\CreditNote;
use App\Domain\Invoices\Models\CreditNoteTax;
use App\Domain\Invoices\Models\InvoiceTax;
use App\Domain\Numbering\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteTax>
 */
class CreditNoteTaxFactory extends Factory
{
    protected $model = CreditNoteTax::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => app(SequenceAllocator::class)->next('credit_note_taxes'),
            'credit_note_id' => CreditNote::factory(),
            'invoice_tax_id' => InvoiceTax::factory(),
            'taxable_amount' => -1000,
            'tax_amount' => -200,
            'created_at' => now(),
        ];
    }
}
