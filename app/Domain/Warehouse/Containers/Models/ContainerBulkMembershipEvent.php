<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Models;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Enums\ContainerBulkMembershipEventType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * @property int $id
 * @property int $stock_level_id
 * @property int|null $source_container_id
 * @property int|null $destination_container_id
 * @property ContainerBulkMembershipEventType $event_type
 * @property string $quantity
 * @property Carbon $occurred_at
 * @property int|null $actor_user_id
 * @property string|null $reason
 * @property string|null $source_quantity_before
 * @property string|null $source_quantity_after
 * @property string|null $destination_quantity_before
 * @property string|null $destination_quantity_after
 * @property Carbon $created_at
 */
final class ContainerBulkMembershipEvent extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'stock_level_id',
        'source_container_id',
        'destination_container_id',
        'event_type',
        'quantity',
        'occurred_at',
        'actor_user_id',
        'reason',
        'source_quantity_before',
        'source_quantity_after',
        'destination_quantity_before',
        'destination_quantity_after',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ContainerBulkMembershipEventType::class,
            'quantity' => 'decimal:2',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'source_quantity_before' => 'decimal:2',
            'source_quantity_after' => 'decimal:2',
            'destination_quantity_before' => 'decimal:2',
            'destination_quantity_after' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<StockLevel, $this> */
    public function stockLevel(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class);
    }

    /** @return BelongsTo<Container, $this> */
    public function sourceContainer(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'source_container_id');
    }

    /** @return BelongsTo<Container, $this> */
    public function destinationContainer(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'destination_container_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected static function booted(): void
    {
        self::creating(function (self $event): void {
            if (bccomp((string) $event->quantity, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Bulk containment event quantity must be greater than zero.',
                ]);
            }
        });

        self::updating(static function (): void {
            throw new LogicException('Bulk containment history is immutable.');
        });

        self::deleting(static function (): void {
            throw new LogicException('Bulk containment history cannot be deleted.');
        });
    }
}
