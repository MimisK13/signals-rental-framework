<?php

namespace App\Models;

use App\Models\Traits\FormatsMoney;
use Database\Factories\CreditNoteTaxFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-tax-rate breakdown row on a credit note projection.
 *
 * @property int $id
 * @property int $credit_note_id
 * @property int $invoice_tax_id
 * @property int|null $tax_rate_id
 * @property int $taxable_amount
 * @property int $tax_amount
 * @property Carbon|null $created_at
 */
class CreditNoteTax extends Model
{
    /** @use HasFactory<CreditNoteTaxFactory> */
    use FormatsMoney, HasFactory;

    public $incrementing = false;

    public $timestamps = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'credit_note_id',
        'invoice_tax_id',
        'tax_rate_id',
        'taxable_amount',
        'tax_amount',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taxable_amount' => 'integer',
            'tax_amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected function moneyFormattingCurrency(): string
    {
        if ($this->relationLoaded('creditNote') && $this->creditNote !== null) {
            return $this->creditNote->currency_code;
        }

        return $this->baseFormattingCurrency();
    }

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * @return BelongsTo<InvoiceTax, $this>
     */
    public function invoiceTax(): BelongsTo
    {
        return $this->belongsTo(InvoiceTax::class);
    }
}
