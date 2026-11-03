<?php

declare(strict_types=1);

namespace App\Domain\Stock\Stocktakes\Services;

use App\Domain\Stock\Models\StockLevel;
use Illuminate\Database\Eloquent\Collection;

final class SerializedStocktakeIdentifierResolver
{
    /** @return Collection<int, StockLevel> */
    public function resolve(string $identifier): Collection
    {
        return StockLevel::query()
            ->with(['asset', 'product', 'store'])
            ->serialized()
            ->where('is_virtual', false)
            ->whereNotNull('asset_id')
            ->where(function ($query) use ($identifier): void {
                $query
                    ->where('barcode', $identifier)
                    ->orWhere('asset_number', $identifier)
                    ->orWhere('serial_number', $identifier);
            })
            ->get();
    }
}
