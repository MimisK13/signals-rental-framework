<?php

namespace App\Services\Assets;

use App\Enums\AssetRentalRevenueAttributionStatus;

final readonly class AssetFinancialsResult
{
    /**
     * @param  list<string>  $unavailableReasons
     */
    public function __construct(
        public int $confirmedAttributedRentalRevenue,
        public AssetRentalRevenueAttributionStatus $revenueStatus,
        public bool $revenueComplete,
        public ?int $operatingExpenses,
        public ?int $netRentalIncome,
        public ?string $costRecoveryRatio,
        public ?int $remainingToBreakEven,
        public ?int $investmentProfit,
        public ?string $investmentRoi,
        public int $rentalCount,
        public int $completedRentalCount,
        public ?int $actualRentalDays,
        public string $currencyCode,
        public array $unavailableReasons = [],
    ) {}

    public function isFinanciallyComplete(): bool
    {
        return $this->unavailableReasons === [];
    }
}
