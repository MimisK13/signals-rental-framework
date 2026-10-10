<?php

namespace App\Models;

use App\Enums\StocktakeStatus;
use Carbon\Carbon;
use Database\Factories\StocktakeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property StocktakeStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 */
class Stocktake extends Model
{
    /** @use HasFactory<StocktakeFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (Stocktake $stocktake): void {
            if (in_array($stocktake->getRawOriginal('status'), [
                StocktakeStatus::Completed->value,
                StocktakeStatus::Cancelled->value,
            ], true)) {
                throw new LogicException('Completed or cancelled Stocktakes are immutable.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Stocktake evidence cannot be deleted.');
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'store_id',
        'status',
        'started_by_user_id',
        'started_at',
        'completed_by_user_id',
        'completed_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => StocktakeStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<User, $this> */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** @return HasMany<StocktakeLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StocktakeLine::class);
    }

    /** @return HasMany<StocktakeObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(StocktakeObservation::class);
    }

    public function isDraft(): bool
    {
        return $this->status === StocktakeStatus::Draft;
    }

    public function isInProgress(): bool
    {
        return $this->status === StocktakeStatus::InProgress;
    }

    public function isCompleted(): bool
    {
        return $this->status === StocktakeStatus::Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status === StocktakeStatus::Cancelled;
    }
}
