<?php

declare(strict_types=1);

namespace App\Domain\Assets\Repairs\Actions;

use App\Domain\Assets\Repairs\Enums\RepairStatus;
use App\Domain\Assets\Repairs\Models\Repair;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Availability\Services\ProductStoreCapacityLock;
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
