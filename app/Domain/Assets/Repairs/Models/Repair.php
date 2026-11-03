<?php

declare(strict_types=1);

namespace App\Domain\Assets\Repairs\Models;

use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetExpense;
use App\Domain\Assets\Models\AssetInspection;
use App\Domain\Assets\Repairs\Database\Factories\RepairFactory;
use App\Domain\Assets\Repairs\Enums\RepairStatus;
use App\Domain\Assets\Repairs\Observers\RepairObserver;
use App\Domain\Attachments\Concerns\HasAttachments;
use App\Domain\Availability\Models\Demand;
use App\Domain\Stock\Enums\StockMethod;
use App\Domain\SupplierBills\Models\SupplierBillItem;
use App\Models\Member;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int|null $asset_id
 * @property int $product_id
 * @property int $store_id
 * @property int $quantity
 * @property RepairStatus $status
 * @property Carbon $reported_at
 * @property Carbon|null $started_at
 * @property Carbon|null $expected_completed_at
 * @property Carbon|null $completed_at
 * @property bool $is_usable
 * @property int|null $reported_by_user_id
 * @property int|null $assigned_to_user_id
 * @property int|null $external_repairer_id
 * @property int|null $asset_inspection_id
 * @property string $description
 * @property string|null $notes
 */
#[ObservedBy(RepairObserver::class)]
class Repair extends Model
{
    /** @use HasFactory<RepairFactory> */
    use HasAttachments, HasFactory;

    protected static function newFactory(): RepairFactory
    {
        return RepairFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (Repair $repair): void {
            $repair->validateScope();
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'asset_id',
        'product_id',
        'store_id',
        'quantity',
        'status',
        'reported_at',
        'started_at',
        'expected_completed_at',
        'completed_at',
        'is_usable',
        'reported_by_user_id',
        'assigned_to_user_id',
        'external_repairer_id',
        'asset_inspection_id',
        'description',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RepairStatus::class,
            'reported_at' => 'datetime',
            'started_at' => 'datetime',
            'expected_completed_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_usable' => 'boolean',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function externalRepairer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'external_repairer_id');
    }

    /** @return BelongsTo<AssetInspection, $this> */
    public function assetInspection(): BelongsTo
    {
        return $this->belongsTo(AssetInspection::class);
    }

    /** @return HasOne<AssetExpense, $this> */
    public function expense(): HasOne
    {
        return $this->hasOne(AssetExpense::class);
    }

    /** @return HasMany<SupplierBillItem, $this> */
    public function supplierBillItems(): HasMany
    {
        return $this->hasMany(SupplierBillItem::class);
    }

    public function isActive(): bool
    {
        return $this->statusEnum() !== RepairStatus::Completed;
    }

    public function statusEnum(): RepairStatus
    {
        $status = $this->getAttribute('status');

        return $status instanceof RepairStatus ? $status : RepairStatus::from((string) $status);
    }

    public function createsAvailabilityDemand(): bool
    {
        return $this->isActive() && ! $this->is_usable;
    }

    public function availabilityStartsAt(): Carbon
    {
        return Carbon::parse($this->started_at ?? $this->reported_at);
    }

    public function availabilityEndsAt(): Carbon
    {
        return $this->completed_at !== null
            ? Carbon::parse($this->completed_at)
            : Carbon::parse(Demand::SENTINEL_DATE);
    }

    public function validateScope(): void
    {
        if ($this->asset_id !== null) {
            $asset = $this->asset ?: Asset::query()->find($this->asset_id);

            if ($asset === null || (int) $asset->product_id !== (int) $this->product_id) {
                throw ValidationException::withMessages([
                    'asset_id' => 'The repair asset must belong to the selected Product.',
                ]);
            }

            $product = $this->product ?: Product::query()->find($this->product_id);

            if ($product?->stock_method !== StockMethod::Serialised || (int) $this->quantity !== 1) {
                throw ValidationException::withMessages([
                    'quantity' => 'Serialized repairs must target one serialized unit.',
                ]);
            }

            return;
        }

        $product = $this->product ?: Product::query()->find($this->product_id);

        if ($product?->stock_method === StockMethod::Serialised) {
            throw ValidationException::withMessages([
                'asset_id' => 'A serialized repair must reference an Asset.',
            ]);
        }

        if ((int) $this->quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Bulk repair quantity must be greater than zero.',
            ]);
        }
    }
}
