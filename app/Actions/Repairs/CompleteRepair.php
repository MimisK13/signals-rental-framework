<?php

namespace App\Actions\Repairs;

use App\Enums\RepairStatus;
use App\Events\AuditableEvent;
use App\Models\Repair;
use App\Services\Availability\ProductStoreCapacityLock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CompleteRepair
{
    public function __invoke(Repair $repair): Repair
    {
        Gate::authorize('update', $repair);

        return DB::transaction(function () use ($repair): Repair {
            $repair->refresh();
            app(ProductStoreCapacityLock::class)->acquire((int) $repair->product_id, (int) $repair->store_id);

            $repair->forceFill([
                'status' => RepairStatus::Completed,
                'completed_at' => $repair->completed_at ?? Carbon::now(),
                'is_usable' => true,
            ])->save();
            event(new AuditableEvent($repair->refresh(), 'repair.completed'));

            return $repair;
        });
    }
}
