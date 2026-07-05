<?php

namespace App\Services\Containers;

use App\Models\Container;
use App\Models\StockLevel;

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

        $container = Container::query()
            ->active()
            ->where(fn ($q) => $q->where('barcode', $term)->orWhere('uuid', $term))
            ->first();

        if ($container !== null) {
            return ['type' => 'container', 'container' => $container];
        }

        $stockLevel = StockLevel::query()
            ->serialized()
            ->where(fn ($q) => $q->where('asset_number', $term)->orWhere('serial_number', $term))
            ->first();

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
