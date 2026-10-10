<?php

namespace App\Services\Assets;

use App\Models\Asset;

final readonly class ProductFinancialsResult
{
    /**
     * @param  list<string>  $unavailableReasons
     * @param  list<array{asset: Asset, financials: AssetFinancialsResult}>  $assetResults
     */
    public function __construct(
        public int $assetCount,
        public ?int $totalAcquisitionCost,
        public ?int $confirmedAttributedRentalRevenue,
        public ?int $operatingExpenses,
        public ?int $netRentalIncome,
        public ?string $costRecoveryRatio,
        public ?int $remainingToBreakEven,
        public ?int $investmentProfit,
        public ?string $investmentRoi,
        public int $assetParticipationCount,
        public int $rentalJobCount,
        public int $completedRentalCount,
        public ?int $actualRentalDays,
        public string $currencyCode,
        public bool $revenueComplete,
        public bool $financialsComplete,
        public int $recoveredAssetCount,
        public int $belowBreakEvenAssetCount,
        public int $unusedAssetCount,
        public int $incompleteAssetCount,
        public array $unavailableReasons = [],
        public array $assetResults = [],
    ) {}
}
