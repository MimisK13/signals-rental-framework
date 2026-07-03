<?php

namespace App\Models;

use App\Models\Traits\FormatsMoney;
use Database\Factories\CreditNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Read-optimised projection of an event-sourced credit note.
 *
 * @property int $id
 * @property int $invoice_id
 * @property string $number
 * @property string $status
 * @property int $subtotal
 * @property int $tax_total
 * @property int $total
 * @property string $currency_code
 * @property string $exchange_rate
 * @property bool $prices_include_tax
 * @property string|null $notes
 * @property Carbon $issued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class CreditNote extends Model
{
    /** @use HasFactory<CreditNoteFactory> */
    use FormatsMoney, HasFactory, SoftDeletes;

    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'invoice_id',
        'number',
        'status',
        'subtotal',
        'tax_total',
        'total',
        'currency_code',
        'exchange_rate',
        'prices_include_tax',
        'notes',
        'issued_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'prices_include_tax' => 'boolean',
            'issued_at' => 'datetime',
        ];
    }

    protected function moneyFormattingCurrency(): string
    {
        return $this->currency_code ?: $this->baseFormattingCurrency();
    }

    /**
     * Absolute credit amount (positive integer minor units).
     */
    public function creditAmount(): int
    {
        return abs($this->total);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasMany<CreditNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    /**
     * @return HasMany<CreditNoteTax, $this>
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(CreditNoteTax::class);
    }
}
