<?php

namespace App\Models;

use App\Enums\InvoiceType;
use App\Models\Traits\FormatsMoney;
use App\Models\Traits\HasStoreScoping;
use App\Services\Invoices\EnsuresInvoiceIsDraft;
use App\Services\Invoices\InvoiceStatusResolver;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Read-optimised projection of the event-sourced invoice lifecycle.
 *
 * @property int $id
 * @property int $state_id
 * @property string $number
 * @property string $type
 * @property bool $is_posting
 * @property string $status
 * @property int $member_id
 * @property int $store_id
 * @property int|null $opportunity_id
 * @property int $subtotal
 * @property int $tax_total
 * @property int $total
 * @property int $amount_paid
 * @property int $amount_outstanding
 * @property int $credited_total
 * @property string $currency_code
 * @property string $exchange_rate
 * @property bool $prices_include_tax
 * @property string|null $notes
 * @property string|null $reference
 * @property list<string>|null $tag_list
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property bool $is_locked
 * @property Carbon|null $overdue_notified_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use FormatsMoney, HasFactory, HasStoreScoping, SoftDeletes;

    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'state_id',
        'number',
        'type',
        'is_posting',
        'status',
        'is_locked',
        'member_id',
        'store_id',
        'opportunity_id',
        'issued_at',
        'due_at',
        'overdue_notified_at',
        'paid_at',
        'subtotal',
        'tax_total',
        'total',
        'amount_paid',
        'amount_outstanding',
        'credited_total',
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
            'credited_total' => 'integer',
            'is_posting' => 'boolean',
            'is_locked' => 'boolean',
            'prices_include_tax' => 'boolean',
            'tag_list' => 'array',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
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

    public function effectiveStatus(): string
    {
        return app(InvoiceStatusResolver::class)->resolve(
            storedStatus: $this->status,
            amountPaid: $this->amount_paid,
            total: $this->total,
            amountOutstanding: $this->amount_outstanding,
            dueAt: $this->due_at,
        );
    }

    public function isOverdue(): bool
    {
        return app(InvoiceStatusResolver::class)->isOverdue(
            storedStatus: $this->status,
            amountOutstanding: $this->amount_outstanding,
            dueAt: $this->due_at,
        );
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeOverdue(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= now('UTC');
        $eligible = app(InvoiceStatusResolver::class)->overdueEligibleStoredStatuses();

        return $query
            ->whereIn('status', $eligible)
            ->where('amount_outstanding', '>', 0)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now);
    }

    /**
     * @throws ValidationException
     */
    public function ensureDraft(): void
    {
        app(EnsuresInvoiceIsDraft::class)->assert($this);
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
     * Effective balance after payments and credit notes.
     */
    public function effectiveOutstanding(): int
    {
        return $this->total - $this->amount_paid - $this->credited_total;
    }

    /**
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * @return HasMany<InvoicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }
}
