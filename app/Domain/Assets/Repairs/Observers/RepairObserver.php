<?php

declare(strict_types=1);

namespace App\Domain\Assets\Repairs\Observers;

use App\Domain\Assets\Repairs\Models\Repair;
use App\Domain\Availability\Services\RepairDemandResolver;

class RepairObserver
{
    public function created(Repair $repair): void
    {
        app(RepairDemandResolver::class)->syncDemands($repair);
    }

    public function updated(Repair $repair): void
    {
        $repair = $repair->refresh();

        if ($repair->asset_id !== null) {
            app(RepairDemandResolver::class)->syncForAsset($repair->asset_id);

            return;
        }

        app(RepairDemandResolver::class)->syncDemands($repair);
    }

    public function deleted(Repair $repair): void
    {
        $resolver = app(RepairDemandResolver::class);

        $resolver->releaseDemands($repair);

        if ($repair->asset_id !== null) {
            $resolver->syncForAsset($repair->asset_id);
        }
    }
}
