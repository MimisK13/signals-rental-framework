<?php

namespace App\Actions\Repairs;

use App\Events\AuditableEvent;
use App\Models\Repair;
use App\Services\Availability\ProductStoreCapacityLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetRepairUsability
{
    public function __invoke(Repair $repair, bool $isUsable): Repair
    {
        Gate::authorize('update', $repair);

        return DB::transaction(function () use ($repair, $isUsable): Repair {
            $repair->refresh();
            app(ProductStoreCapacityLock::class)->acquire((int) $repair->product_id, (int) $repair->store_id);

            $repair->forceFill(['is_usable' => $isUsable])->save();
            event(new AuditableEvent($repair->refresh(), 'repair.usability_changed'));

            return $repair;
        });
    }
}
