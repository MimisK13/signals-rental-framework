<?php

namespace App\Actions\Repairs;

use App\Enums\RepairStatus;
use App\Events\AuditableEvent;
use App\Models\Repair;
use App\Services\Availability\ProductStoreCapacityLock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StartRepair
{
    public function __invoke(Repair $repair): Repair
    {
        Gate::authorize('update', $repair);

        return DB::transaction(function () use ($repair): Repair {
            $repair->refresh();
            app(ProductStoreCapacityLock::class)->acquire((int) $repair->product_id, (int) $repair->store_id);

            $repair->forceFill([
                'status' => RepairStatus::InProgress,
                'started_at' => $repair->started_at ?? Carbon::now(),
            ])->save();
            event(new AuditableEvent($repair->refresh(), 'repair.started'));

            return $repair;
        });
    }
}
