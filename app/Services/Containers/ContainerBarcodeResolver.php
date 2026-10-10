<?php

namespace App\Services\Containers;

use App\Models\Container;
use App\Models\StockLevel;
use Illuminate\Validation\ValidationException;

/**
 * Resolves a scanned or typed barcode / asset number to a container or housing
 * asset (P55 warehouse scan wedge — not the Phase-6 scanning abstraction layer).
 */
class ContainerBarcodeResolver
{
    /**
     * @return array{type: 'container', container: Container}|array{type: 'housing', stock_level: StockLevel, container: Container|null}|null
     */
    public function resolve(string $barcode): ?array
    {
        $term = trim($barcode);

        if ($term === '') {
            return null;
        }

        $containers = Container::query()
            ->active()
            ->where(fn ($q) => $q->where('barcode', $term)->orWhere('uuid', $term))
            ->get();

        $stockLevels = StockLevel::query()
            ->serialized()
            ->where(fn ($q) => $q
                ->where('asset_number', $term)
                ->orWhere('serial_number', $term)
                ->orWhere('barcode', $term))
            ->get();

        $container = $containers->first();

        // A product-backed Container intentionally inherits its scan identity
        // from its housing StockLevel. Treat those two rows as one physical
        // target, while still rejecting collisions with another StockLevel.
        if ($container !== null) {
            $stockLevels = $stockLevels->reject(
                fn (StockLevel $stockLevel): bool => (int) $container->serialised_item_id === (int) $stockLevel->id
            );
        }

        if ($containers->count() + $stockLevels->count() > 1) {
            throw ValidationException::withMessages([
                'scan' => __('Ambiguous identifier: ":number" matches multiple physical records.', ['number' => $term]),
            ]);
        }

        if ($container !== null) {
            return ['type' => 'container', 'container' => $container];
        }

        $stockLevel = $stockLevels->first();

        if ($stockLevel === null) {
            return null;
        }

        $housingContainer = Container::query()
            ->active()
            ->where('serialised_item_id', $stockLevel->id)
            ->first();

        return [
            'type' => 'housing',
            'stock_level' => $stockLevel,
            'container' => $housingContainer,
        ];
    }
}
