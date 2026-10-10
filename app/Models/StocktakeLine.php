<?php

namespace App\Models;

use App\Enums\StocktakeStatus;
use Database\Factories\StocktakeLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class StocktakeLine extends Model
{
    /** @use HasFactory<StocktakeLineFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (StocktakeLine $line): void {
            $status = Stocktake::query()->whereKey($line->stocktake_id)->value('status');

            if (in_array($status, [StocktakeStatus::Completed, StocktakeStatus::Cancelled], true)) {
                throw new LogicException('Completed or cancelled Stocktake lines are immutable.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Stocktake lines cannot be deleted.');
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'stocktake_id',
        'stock_level_id',
        'asset_id',
        'product_name_snapshot',
        'stock_level_label_snapshot',
        'quantity_held_snapshot',
        'asset_number_snapshot',
        'serial_number_snapshot',
        'barcode_snapshot',
        'container_id',
        'container_name_snapshot',
        'container_barcode_snapshot',
        'lifecycle_status_snapshot',
        'repair_status_snapshot',
        'inspection_status_snapshot',
        'asset_usage_id_snapshot',
        'accepted_observation_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_held_snapshot' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Stocktake, $this> */
    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class);
    }

    /** @return BelongsTo<StockLevel, $this> */
    public function stockLevel(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Container, $this> */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    /** @return BelongsTo<StocktakeObservation, $this> */
    public function acceptedObservation(): BelongsTo
    {
        return $this->belongsTo(StocktakeObservation::class, 'accepted_observation_id');
    }

    /** @return HasMany<StocktakeObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(StocktakeObservation::class);
    }

    public function isCounted(): bool
    {
        return $this->accepted_observation_id !== null;
    }

    public function observationDelta(): ?string
    {
        if ($this->asset_id !== null || ! $this->acceptedObservation || $this->acceptedObservation->counted_quantity === null) {
            return null;
        }

        return bcsub(
            (string) $this->acceptedObservation->counted_quantity,
            (string) $this->quantity_held_snapshot,
            2,
        );
    }
}
