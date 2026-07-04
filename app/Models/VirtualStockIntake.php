<?php

namespace App\Models;

use App\Models\Traits\FormatsMoney;
use App\Models\Traits\HasStoreScoping;
use Database\Factories\VirtualStockIntakeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Read-optimised projection of an event-sourced virtual stock intake.
 *
 * @property int $id
 * @property int $state_id
 * @property string $number
 * @property string $status
 * @property int|null $purchase_order_id
 * @property int|null $purchase_order_item_id
 * @property int $product_id
 * @property int $store_id
 * @property int $quantity
 * @property Carbon $available_from
 * @property Carbon $available_until
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $received_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $cancelled_at
 * @property int $unit_cost
 * @property string $currency_code
 * @property string|null $cost_apportionment_strategy
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $demand_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class VirtualStockIntake extends Model
{
    /** @use HasFactory<VirtualStockIntakeFactory> */
    use FormatsMoney, HasFactory, HasStoreScoping, SoftDeletes;

    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'state_id',
        'number',
        'status',
        'purchase_order_id',
        'purchase_order_item_id',
        'product_id',
        'store_id',
        'quantity',
        'available_from',
        'available_until',
        'confirmed_at',
        'received_at',
        'returned_at',
        'cancelled_at',
        'unit_cost',
        'currency_code',
        'cost_apportionment_strategy',
        'notes',
        'created_by',
        'demand_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'confirmed_at' => 'datetime',
            'received_at' => 'datetime',
            'returned_at' => 'datetime',
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
                'virtual_stock_intake' => ['Only draft virtual stock intakes can be edited.'],
            ]);
        }
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<VirtualStockCostOverride, $this>
     */
    public function costOverrides(): HasMany
    {
        return $this->hasMany(VirtualStockCostOverride::class);
    }

    /**
     * @return HasMany<OpportunityCost, $this>
     */
    public function apportionedCosts(): HasMany
    {
        return $this->hasMany(OpportunityCost::class, 'virtual_stock_intake_id');
    }
}
