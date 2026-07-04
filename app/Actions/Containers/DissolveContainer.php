<?php

namespace App\Actions\Containers;

use App\Data\Containers\ContainerData;
use App\Enums\ContainerDissolveReason;
use App\Enums\ContainerItemUnpackReason;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\StockLevel;
use App\Services\Availability\ContainerDemandResolver;
use App\Services\Containers\ContainerEventRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Manually dissolve a container — soft-closing every active membership, releasing
 * container demands, and cascading to nested containers (serialised-containers.md
 * §"Dissolved" / kit manual retirement).
 *
 * Open and sealed containers may be dissolved. Sealed containers are dissolved
 * directly (an implicit unseal — contents are being released, not modified).
 * Dispatched or returned kit containers cannot be manually dissolved.
 */
class DissolveContainer
{
    public function __invoke(Container $container): ContainerData
    {
        Gate::authorize('dissolve', $container);

        if ($container->status === ContainerStatus::Dissolved) {
            throw ValidationException::withMessages([
                'container' => __('This container is already dissolved.'),
            ]);
        }

        if (in_array($container->status, [ContainerStatus::Dispatched, ContainerStatus::Returned], true)) {
            throw ValidationException::withMessages([
                'container' => __('Dispatched or returned containers cannot be manually dissolved.'),
            ]);
        }

        return DB::transaction(function () use ($container): ContainerData {
            $itemCount = $this->dissolveTree($container);

            app(ContainerEventRecorder::class)->dissolved(
                $container->fresh() ?? $container,
                ContainerDissolveReason::Manual,
                $itemCount,
            );

            return ContainerData::fromModel($container->fresh() ?? $container);
        });
    }

    /**
     * Depth-first dissolve of a container and any nested active containers.
     */
    protected function dissolveTree(Container $container): int
    {
        $container->refresh();
        $closed = 0;

        foreach ($container->activeItems()->with('serialisedItem')->get() as $item) {
            $nested = Container::query()
                ->active()
                ->where('serialised_item_id', $item->serialised_item_id)
                ->first();

            if ($nested !== null && $nested->id !== $container->id) {
                $closed += $this->dissolveTree($nested);
            }
        }

        foreach ($container->activeItems()->get() as $item) {
            $this->closeMembership($container, $item);
            $closed++;
        }

        $container->update([
            'status' => ContainerStatus::Dissolved,
            'dissolved_at' => Carbon::now('UTC'),
            'dissolved_by_user_id' => Auth::id(),
            'dissolved_reason' => ContainerDissolveReason::Manual->value,
            'parent_container_id' => null,
        ]);

        return $closed;
    }

    protected function closeMembership(Container $container, ContainerItem $item): void
    {
        $item->forceFill([
            'unpacked_at' => Carbon::now('UTC'),
            'unpacked_reason' => ContainerItemUnpackReason::Dissolved,
        ])->save();

        StockLevel::query()
            ->whereKey($item->serialised_item_id)
            ->update([
                'container_stock_level_id' => null,
                'container_mode' => null,
            ]);

        $item->setRelation('container', $container);
        app(ContainerDemandResolver::class)->releaseDemands($item);
    }
}
