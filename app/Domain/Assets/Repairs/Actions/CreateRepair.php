<?php

declare(strict_types=1);

namespace App\Domain\Assets\Repairs\Actions;

use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Repairs\Models\Repair;
use App\Domain\Availability\Services\ProductStoreCapacityLock;
use App\Events\AuditableEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateRepair
{
    /** @param array<string, mixed> $attributes */
    public function __invoke(array $attributes): Repair
    {
        $asset = isset($attributes['asset_id'])
            ? Asset::query()->with('currentStockLevel')->findOrFail((int) $attributes['asset_id'])
            : null;

        if ($asset !== null) {
            $attributes['product_id'] = $asset->product_id;
            $attributes['quantity'] = 1;
            $attributes['store_id'] ??= $asset->currentStockLevel?->store_id;
        }

        if (($attributes['store_id'] ?? null) === null) {
            throw ValidationException::withMessages([
                'store_id' => 'A Store is required for a repair.',
            ]);
        }

        Gate::authorize('create', new Repair($attributes));

        $attributes['reported_at'] ??= Carbon::now();
        $attributes['reported_by_user_id'] ??= auth()->id();
        $attributes['status'] ??= 'open';
        $attributes['is_usable'] ??= true;

        return DB::transaction(function () use ($attributes): Repair {
            app(ProductStoreCapacityLock::class)
                ->acquire((int) $attributes['product_id'], (int) $attributes['store_id']);

            $repair = Repair::query()->create($attributes);
            event(new AuditableEvent($repair, 'repair.created'));

            return $repair->refresh();
        });
    }
}
