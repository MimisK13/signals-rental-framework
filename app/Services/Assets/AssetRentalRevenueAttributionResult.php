<?php

namespace App\Services\Assets;

use App\Enums\AssetRentalRevenueAttributionStatus;

final readonly class AssetRentalRevenueAttributionResult
{
    /**
     * @param  array<int, int>  $allocations  Asset ID to net revenue in minor units.
     */
    public function __construct(
        public AssetRentalRevenueAttributionStatus $status,
        public array $allocations = [],
        public ?string $currencyCode = null,
    ) {}

    public function isAvailable(): bool
    {
        return $this->status === AssetRentalRevenueAttributionStatus::Available;
    }

    public function isAmbiguous(): bool
    {
        return $this->status === AssetRentalRevenueAttributionStatus::Ambiguous;
    }

    public function isNotApplicable(): bool
    {
        return $this->status === AssetRentalRevenueAttributionStatus::NotApplicable;
    }

    public function amountForAsset(int $assetId): ?int
    {
        return $this->allocations[$assetId] ?? null;
    }

    public function total(): ?int
    {
        if (! $this->isAvailable()) {
            return null;
        }

        return array_sum($this->allocations);
    }
}
