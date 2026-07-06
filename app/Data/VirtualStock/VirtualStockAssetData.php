<?php

namespace App\Data\VirtualStock;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\StockLevel;
use Spatie\LaravelData\Data;

class VirtualStockAssetData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public string $asset_number,
        public ?string $serial_number,
        public string $virtual_asset_status,
        public string $virtual_asset_status_label,
        public ?string $starts_at,
        public ?string $ends_at,
        public string $quantity_held,
        public string $quantity_allocated,
    ) {}

    public static function fromModel(StockLevel $asset): self
    {
        $status = (string) ($asset->virtual_asset_status ?? config('virtual_stock.virtual_asset_default_status', 'available'));
        $statusLabel = (string) (config('virtual_stock.virtual_asset_statuses.'.$status.'.label') ?? $status);

        return new self(
            id: $asset->id,
            asset_number: (string) $asset->asset_number,
            serial_number: $asset->serial_number,
            virtual_asset_status: $status,
            virtual_asset_status_label: $statusLabel,
            starts_at: self::formatNullableTimestamp($asset->starts_at),
            ends_at: self::formatNullableTimestamp($asset->ends_at),
            quantity_held: number_format((float) ($asset->quantity_held ?? 0), 1, '.', ''),
            quantity_allocated: number_format((float) ($asset->quantity_allocated ?? 0), 1, '.', ''),
        );
    }
}
