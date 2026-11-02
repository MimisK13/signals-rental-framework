<?php

declare(strict_types=1);

namespace App\Domain\Assets\Models;

use App\Domain\Assets\Database\Factories\AssetFactory;
use App\Domain\Assets\Enums\AssetLifecycleStatus;
use App\Domain\Assets\Enums\AssetOwnershipType;
use App\Domain\PurchaseOrders\Models\PurchaseOrderItem;
use App\Domain\Repairs\Models\Repair;
use App\Domain\Stock\Models\StockLevel;
use App\Models\Member;
use App\Models\Product;
use App\Models\Traits\FormatsMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $product_id
 * @property AssetLifecycleStatus $lifecycle_status
 * @property AssetOwnershipType $ownership_type
 * @property int|null $owner_member_id
 * @property Carbon|null $acquired_at
 * @property int|null $acquisition_amount
 * @property string|null $acquisition_currency_code
 * @property string|null $acquisition_exchange_rate
 * @property string|null $base_currency_code
 * @property int|null $base_acquisition_amount
 * @property int|null $supplier_member_id
 * @property int|null $purchase_order_item_id
 * @property int|null $estimated_resale_value
 * @property string|null $estimated_resale_currency_code
 */
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use FormatsMoney, HasFactory;

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (Asset $asset): void {
            if ($asset->exists && $asset->isDirty('lifecycle_status')) {
                throw ValidationException::withMessages([
                    'lifecycle_status' => 'Asset lifecycle changes must use an explicit lifecycle action.',
                ]);
            }
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'lifecycle_status',
        'ownership_type',
        'owner_member_id',
        'acquired_at',
        'acquisition_amount',
        'acquisition_currency_code',
        'acquisition_exchange_rate',
        'base_currency_code',
        'base_acquisition_amount',
        'supplier_member_id',
        'purchase_order_item_id',
        'estimated_resale_value',
        'estimated_resale_currency_code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ownership_type' => AssetOwnershipType::class,
            'lifecycle_status' => AssetLifecycleStatus::class,
            'acquired_at' => 'datetime',
            'acquisition_amount' => 'integer',
            'base_acquisition_amount' => 'integer',
            'estimated_resale_value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'owner_member_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'supplier_member_id');
    }

    /**
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * @return HasOne<StockLevel, $this>
     */
    public function currentStockLevel(): HasOne
    {
        return $this->hasOne(StockLevel::class);
    }

    /**
     * @return HasMany<AssetExpense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(AssetExpense::class);
    }

    /**
     * @return HasMany<AssetUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(AssetUsage::class);
    }

    /**
     * @return HasMany<Repair, $this>
     */
    public function repairs(): HasMany
    {
        return $this->hasMany(Repair::class);
    }

    /** @return HasMany<AssetInspection, $this> */
    public function inspections(): HasMany
    {
        return $this->hasMany(AssetInspection::class);
    }

    /** @return HasMany<AssetLifecycleEvent, $this> */
    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(AssetLifecycleEvent::class);
    }

    public function isOperationallyActive(): bool
    {
        return ($this->lifecycle_status ?? AssetLifecycleStatus::Active) === AssetLifecycleStatus::Active;
    }

    public function lifecycleStatus(): AssetLifecycleStatus
    {
        return $this->lifecycle_status ?? AssetLifecycleStatus::Active;
    }

    protected function moneyFormattingCurrency(): string
    {
        return $this->base_currency_code ?: $this->baseFormattingCurrency();
    }
}
