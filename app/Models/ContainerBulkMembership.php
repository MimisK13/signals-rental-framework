<?php

namespace App\Models;

use Database\Factories\ContainerBulkMembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;

class ContainerBulkMembership extends Model
{
    /** @use HasFactory<ContainerBulkMembershipFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'container_id',
        'stock_level_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    /** @return BelongsTo<Container, $this> */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    /** @return BelongsTo<StockLevel, $this> */
    public function stockLevel(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $membership): void {
            if (bccomp((string) $membership->quantity, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Bulk membership quantity must be greater than zero.',
                ]);
            }

            $stockLevel = StockLevel::query()->with('product')->findOrFail($membership->stock_level_id);
            if (! $stockLevel->isEligibleForBulkContainment()) {
                throw ValidationException::withMessages([
                    'stock_level_id' => 'Only physical bulk StockLevels can be contained in bulk.',
                ]);
            }

            $container = Container::query()->with('serialisedItem')->findOrFail($membership->container_id);
            if ($container->effectiveStoreId() !== (int) $stockLevel->store_id) {
                throw ValidationException::withMessages([
                    'container_id' => 'The Container and StockLevel must belong to the same Store.',
                ]);
            }

            $existingContained = ContainerBulkMembership::query()
                ->where('stock_level_id', $membership->stock_level_id)
                ->when($membership->exists, fn ($query) => $query->whereKeyNot($membership->getKey()))
                ->sum('quantity');
            $resultingContained = bcadd((string) $existingContained, (string) $membership->quantity, 2);

            if (bccomp($resultingContained, (string) $stockLevel->quantity_held, 2) > 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Bulk containment cannot exceed the StockLevel quantity held.',
                ]);
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Bulk membership changes must be performed by a domain Action.');
        });
    }
}
