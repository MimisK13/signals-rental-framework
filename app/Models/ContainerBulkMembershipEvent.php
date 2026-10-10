<?php

namespace App\Models;

use App\Enums\ContainerBulkMembershipEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;

class ContainerBulkMembershipEvent extends Model
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
        static::creating(function (self $event): void {
            if (bccomp((string) $event->quantity, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Bulk containment event quantity must be greater than zero.',
                ]);
            }
        });

        static::updating(static function (): void {
            throw new LogicException('Bulk containment history is immutable.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Bulk containment history cannot be deleted.');
        });
    }
}
