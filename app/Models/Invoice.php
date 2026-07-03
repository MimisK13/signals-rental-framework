<?php

namespace App\Models;

use App\Enums\InvoiceType;
use App\Models\Traits\FormatsMoney;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Read-optimised projection of the event-sourced invoice lifecycle.
 *
 * @property int $id
 * @property int $state_id
 * @property string $number
 * @property string $type
 * @property string $status
 * @property int $member_id
 * @property int $store_id
 * @property int|null $opportunity_id
 * @property int $subtotal
 * @property int $tax_total
 * @property int $total
 * @property int $amount_paid
 * @property int $amount_outstanding
 * @property string $currency_code
 * @property string $exchange_rate
 * @property bool $prices_include_tax
 * @property string|null $notes
 * @property string|null $reference
 * @property list<string>|null $tag_list
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use FormatsMoney, HasFactory, SoftDeletes;

    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'state_id',
        'number',
        'type',
        'status',
        'member_id',
        'store_id',
        'opportunity_id',
        'issued_at',
        'due_at',
        'paid_at',
        'subtotal',
        'tax_total',
        'total',
        'amount_paid',
        'amount_outstanding',
        'currency_code',
        'exchange_rate',
        'prices_include_tax',
        'notes',
        'reference',
        'tag_list',
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
            'amount_paid' => 'integer',
            'amount_outstanding' => 'integer',
            'prices_include_tax' => 'boolean',
            'tag_list' => 'array',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected function moneyFormattingCurrency(): string
    {
        return $this->currency_code ?: $this->baseFormattingCurrency();
    }

    public function typeEnum(): InvoiceType
    {
        return InvoiceType::from($this->type);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<InvoiceTax, $this>
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(InvoiceTax::class);
    }

    /**
     * @return HasMany<InvoicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }
}
