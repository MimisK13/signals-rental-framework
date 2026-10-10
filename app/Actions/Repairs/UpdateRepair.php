<?php

namespace App\Actions\Repairs;

use App\Events\AuditableEvent;
use App\Models\Repair;
use App\Services\Availability\ProductStoreCapacityLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateRepair
{
    /** @param array<string, mixed> $attributes */
    public function __invoke(Repair $repair, array $attributes): Repair
    {
        Gate::authorize('update', $repair);

        return DB::transaction(function () use ($repair, $attributes): Repair {
            $repair->refresh();
            app(ProductStoreCapacityLock::class)->acquire((int) $repair->product_id, (int) ($attributes['store_id'] ?? $repair->store_id));

            $repair->fill($attributes)->save();
            event(new AuditableEvent($repair->refresh(), 'repair.updated'));

            return $repair;
        });
    }
}
