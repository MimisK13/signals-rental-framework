<?php

declare(strict_types=1);

namespace App\Domain\Repairs\Observers;

use App\Domain\Repairs\Models\Repair;
use App\Services\Availability\RepairDemandResolver;

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
