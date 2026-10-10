<?php

namespace App\Actions\Repairs;

use App\Enums\RepairStatus;
use App\Events\AuditableEvent;
use App\Models\Repair;
use App\Services\Availability\ProductStoreCapacityLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MarkRepairUnrepairable
{
    public function __invoke(Repair $repair): Repair
    {
        Gate::authorize('update', $repair);

        return DB::transaction(function () use ($repair): Repair {
            $repair->refresh();
            app(ProductStoreCapacityLock::class)->acquire((int) $repair->product_id, (int) $repair->store_id);

            $repair->forceFill([
                'status' => RepairStatus::Unrepairable,
                'is_usable' => false,
            ])->save();

            event(new AuditableEvent($repair->refresh(), 'repair.unrepairable'));

            return $repair;
        });
    }
}
