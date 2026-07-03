<?php

namespace App\Models;

use App\Enums\TaxResultType;
use App\Models\Traits\FormatsMoney;
use Database\Factories\CreditNoteItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-optimised projection of a credit note line item.
 *
 * @property int $id
 * @property int $credit_note_id
 * @property int $invoice_item_id
 * @property string $description
 * @property string $quantity
 * @property int $unit_price
 * @property int $discount
 * @property int $tax
 * @property int $total
 * @property string $tax_treatment
 * @property int|null $product_tax_class_id
 * @property int $sort_order
 */
class CreditNoteItem extends Model
{
    /** @use HasFactory<CreditNoteItemFactory> */
    use FormatsMoney, HasFactory;

    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'credit_note_id',
        'invoice_item_id',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'total',
        'tax_treatment',
        'product_tax_class_id',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected function moneyFormattingCurrency(): string
    {
        if ($this->relationLoaded('creditNote') && $this->creditNote !== null) {
            return $this->creditNote->currency_code;
        }

        return $this->baseFormattingCurrency();
    }

    public function taxTreatmentEnum(): TaxResultType
    {
        return TaxResultType::from($this->tax_treatment);
    }

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * @return BelongsTo<InvoiceItem, $this>
     */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}
