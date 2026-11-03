<?php

declare(strict_types=1);

namespace App\Domain\VirtualStock\Services;

use App\Domain\Availability\Enums\DemandPhase;
use App\Domain\Availability\Models\Demand;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\VirtualStock\Models\VirtualStockIntake;
use Illuminate\Support\Carbon;

/**
 * Retires serialised virtual assets and voids their per-asset supply demands.
 */
class VirtualStockAssetRetirer
{
    public function __construct(
        private readonly VirtualAssetStatusRegistry $assetStatuses,
    ) {}

    public function retireForIntake(VirtualStockIntake $intake, ?Carbon $retiredAt = null): void
    {
        $retiredAt ??= Carbon::now('UTC');
        $retiredStatus = 'retired';
        $this->assetStatuses->assertKnown($retiredStatus);

        StockLevel::query()
            ->where('virtual_stock_intake_id', $intake->id)
            ->where('is_virtual', true)
            ->each(function (StockLevel $asset) use ($retiredStatus, $retiredAt): void {
                $currentStatus = $asset->virtual_asset_status ?? $this->assetStatuses->defaultStatus();

                if ($this->assetStatuses->isTerminal($currentStatus)) {
                    return;
                }

                if ($this->assetStatuses->canTransitionTo($currentStatus, $retiredStatus)) {
                    $asset->forceFill([
                        'virtual_asset_status' => $retiredStatus,
                        'ends_at' => $retiredAt,
                    ])->saveQuietly();
                }
            });

        Demand::query()
            ->where('source_type', 'virtual_stock_intake')
            ->where('source_id', $intake->id)
            ->whereNotNull('asset_id')
            ->update([
                'phase' => DemandPhase::Void->value,
                'is_active' => false,
            ]);
    }
}
