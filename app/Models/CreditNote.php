<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Models\Traits\FormatsMoney;
use App\Models\Traits\HasStoreScoping;
use App\Services\SchemaBuilder;
use Database\Factories\CreditNoteFactory;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int $store_id
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
class CreditNote extends Model implements HasSchema
{
    /** @use HasFactory<CreditNoteFactory> */
    use FormatsMoney, HasFactory, HasStoreScoping, SoftDeletes;

    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'invoice_id',
        'store_id',
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
     * @param  Builder<CreditNote>  $query
     * @return Builder<CreditNote>
     */
    public function scopeOfStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * @param  Builder<CreditNote>  $query
     * @return Builder<CreditNote>
     */
    public function scopeForAccessibleStores(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query;
        }

        $storeIds = $user->accessibleStoreIds();

        if ($storeIds === null) {
            return $query;
        }

        return $query->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->whereIn('store_id', $storeIds));
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('number')->label('Number')->searchable()->filterable()->sortable();
        $builder->enum('status')->label('Status')->filterable()->sortable()->groupable();
        $builder->relation('invoice_id')->label('Invoice')
            ->relation('invoice', 'belongsTo', Invoice::class, 'number')
            ->filterable()->sortable();
        $builder->relation('store_id')->label('Store')
            ->relation('store', 'belongsTo', Store::class, 'name')
            ->filterable()->sortable();
        $builder->currency('subtotal')->label('Subtotal')->sortable();
        $builder->currency('tax_total')->label('Tax Total')->sortable();
        $builder->currency('total')->label('Total')->sortable();
        $builder->string('currency_code')->label('Currency')->filterable();
        $builder->datetime('issued_at')->label('Issued')->sortable()->filterable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
    }
}
