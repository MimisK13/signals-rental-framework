<?php

namespace App\Models;

use App\Enums\StocktakeObservationType;
use Database\Factories\StocktakeObservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** @property StocktakeObservationType $observation_type */
class StocktakeObservation extends Model
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

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Stocktake observations are immutable.');
        });

        static::deleting(static function (): void {
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
