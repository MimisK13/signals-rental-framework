<?php

namespace App\Models;

use App\Models\Traits\FormatsMoney;
use App\Models\Traits\HasStoreScoping;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Read-optimised projection of an event-sourced purchase order.
 *
 * @property int $id
 * @property int $state_id
 * @property string $number
 * @property string $po_type
 * @property string $status
 * @property int $supplier_id
 * @property int $store_id
 * @property int|null $opportunity_id
 * @property int|null $shortage_resolution_id
 * @property int $subtotal
 * @property int $tax_total
 * @property int $total
 * @property int $rounding_adjustment
 * @property string $currency_code
 * @property string $exchange_rate
 * @property Carbon|null $expected_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $reference
 * @property string|null $notes
 * @property string|null $supplier_reference
 * @property Carbon|null $sent_at
 * @property Carbon|null $received_at
 * @property Carbon|null $cancelled_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use FormatsMoney, HasFactory, HasStoreScoping, SoftDeletes;

    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'state_id',
        'number',
        'po_type',
        'status',
        'supplier_id',
        'store_id',
        'opportunity_id',
        'shortage_resolution_id',
        'subtotal',
        'tax_total',
        'total',
        'rounding_adjustment',
        'currency_code',
        'exchange_rate',
        'expected_at',
        'starts_at',
        'ends_at',
        'reference',
        'notes',
        'supplier_reference',
        'sent_at',
        'received_at',
        'cancelled_at',
        'created_by',
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
            'rounding_adjustment' => 'integer',
            'expected_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected function moneyFormattingCurrency(): string
    {
        return $this->currency_code ?: $this->baseFormattingCurrency();
    }

    public function ensureDraft(): void
    {
        if ($this->status !== 'draft') {
            throw ValidationException::withMessages([
                'purchase_order' => ['Only draft purchase orders can be edited.'],
            ]);
        }
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'supplier_id');
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }
}
