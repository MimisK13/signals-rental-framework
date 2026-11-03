<?php

declare(strict_types=1);

namespace App\Domain\Stock\Stocktakes\Models;

use App\Domain\Assets\Models\Asset;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stock\Stocktakes\Database\Factories\StocktakeObservationFactory;
use App\Domain\Stock\Stocktakes\Enums\StocktakeObservationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $stocktake_id
 * @property int|null $stocktake_line_id
 * @property int|null $asset_id
 * @property int|null $stock_level_id
 * @property string|null $identifier
 * @property StocktakeObservationType $observation_type
 * @property string|null $counted_quantity
 * @property int|null $counted_by_user_id
 * @property Carbon $counted_at
 * @property string|null $notes
 * @property int|null $store_id_snapshot
 * @property int|null $container_id_snapshot
 * @property int|null $asset_usage_id_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $asset_number_snapshot
 * @property string|null $serial_number_snapshot
 * @property string|null $barcode_snapshot
 * @property string|null $lifecycle_status_snapshot
 * @property string|null $container_name_snapshot
 * @property string|null $container_barcode_snapshot
 * @property string|null $repair_status_snapshot
 * @property string|null $inspection_status_snapshot
 * @property-read Stocktake $stocktake
 * @property-read StocktakeLine|null $line
 * @property-read Asset|null $asset
 * @property-read StockLevel|null $stockLevel
 * @property-read User|null $countedBy
 */
final class StocktakeObservation extends Model
{
    /** @use HasFactory<StocktakeObservationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'stocktake_id',
        'stocktake_line_id',
        'asset_id',
        'stock_level_id',
        'identifier',
        'observation_type',
        'counted_quantity',
        'counted_by_user_id',
        'counted_at',
        'notes',
        'asset_number_snapshot',
        'serial_number_snapshot',
        'barcode_snapshot',
        'lifecycle_status_snapshot',
        'store_id_snapshot',
        'container_id_snapshot',
        'container_name_snapshot',
        'container_barcode_snapshot',
        'repair_status_snapshot',
        'inspection_status_snapshot',
        'asset_usage_id_snapshot',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'observation_type' => StocktakeObservationType::class,
            'counted_quantity' => 'decimal:2',
            'counted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): StocktakeObservationFactory
    {
        return StocktakeObservationFactory::new();
    }

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw new LogicException('Stocktake observations are immutable.');
        });

        self::deleting(static function (): void {
            throw new LogicException('Stocktake observations cannot be deleted.');
        });
    }

    /** @return BelongsTo<Stocktake, $this> */
    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class);
    }

    /** @return BelongsTo<StocktakeLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(StocktakeLine::class, 'stocktake_line_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<StockLevel, $this> */
    public function stockLevel(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class);
    }

    public function isSerializedScan(): bool
    {
        return $this->observation_type === StocktakeObservationType::SerializedScan;
    }

    public function isUnresolved(): bool
    {
        return $this->observation_type === StocktakeObservationType::UnresolvedItem;
    }

    /** @return BelongsTo<User, $this> */
    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by_user_id');
    }
}
