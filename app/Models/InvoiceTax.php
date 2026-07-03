<?php

namespace App\Models;

use App\Models\Traits\FormatsMoney;
use Database\Factories\InvoiceTaxFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-tax-rate breakdown row on an invoice projection.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $tax_rate_id
 * @property int $taxable_amount
 * @property int $tax_amount
 * @property string|null $rate_percentage
 * @property string|null $tax_rate_name
 * @property string|null $result_type
 * @property string|null $legal_text
 * @property Carbon|null $created_at
 */
class InvoiceTax extends Model
{
    /** @use HasFactory<InvoiceTaxFactory> */
    use FormatsMoney, HasFactory;

    /**
     * The PK is application-allocated (baked into the InvoiceGenerated event),
     * so Eloquent must not auto-increment it.
     */
    public $incrementing = false;

    public $timestamps = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'invoice_id',
        'tax_rate_id',
        'taxable_amount',
        'tax_amount',
        'rate_percentage',
        'tax_rate_name',
        'result_type',
        'legal_text',
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
        if ($this->relationLoaded('invoice') && $this->invoice !== null) {
            return $this->invoice->currency_code;
        }

        return $this->baseFormattingCurrency();
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
