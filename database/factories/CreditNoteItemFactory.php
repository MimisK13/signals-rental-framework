<?php

namespace Database\Factories;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\InvoiceItem;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteItem>
 */
class CreditNoteItemFactory extends Factory
{
    protected $model = CreditNoteItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => app(SequenceAllocator::class)->next('credit_note_items'),
            'credit_note_id' => CreditNote::factory(),
            'invoice_item_id' => InvoiceItem::factory(),
            'description' => fake()->sentence(3),
            'quantity' => '1.00',
            'unit_price' => -1000,
            'discount' => 0,
            'tax' => -200,
            'total' => -1000,
            'tax_treatment' => 'standard',
            'sort_order' => 0,
        ];
    }
}
