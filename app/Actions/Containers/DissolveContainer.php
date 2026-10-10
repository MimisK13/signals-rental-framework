<?php

namespace App\Actions\Containers;

use App\Concerns\LocksModelsForMutation;
use App\Data\Containers\ContainerData;
use App\Enums\ContainerDissolveReason;
use App\Enums\ContainerStatus;
use App\Events\Containers\BeforeContainerDissolved;
use App\Events\Containers\ContainerDissolved;
use App\Models\Container;
use App\Services\Containers\ContainerDissolveService;
use App\Services\Containers\ContainerEventRecorder;
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
    use LocksModelsForMutation;

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

        BeforeContainerDissolved::dispatch($container);

        return DB::transaction(function () use ($container): ContainerData {
            $locked = $this->lockModelForMutation($container);

            if ($locked->status === ContainerStatus::Dissolved) {
                throw ValidationException::withMessages([
                    'container' => __('This container is already dissolved.'),
                ]);
            }

            if (in_array($locked->status, [ContainerStatus::Dispatched, ContainerStatus::Returned], true)) {
                throw ValidationException::withMessages([
                    'container' => __('Dispatched or returned containers cannot be manually dissolved.'),
                ]);
            }

            $itemCount = app(ContainerDissolveService::class)->dissolveTree(
                $locked,
                ContainerDissolveReason::Manual,
            );

            $fresh = $locked->fresh() ?? $locked;

            app(ContainerEventRecorder::class)->dissolved(
                $fresh,
                ContainerDissolveReason::Manual,
                $itemCount,
            );

            ContainerDissolved::dispatch($fresh, $itemCount);

            return ContainerData::fromModel($fresh);
        });
    }
}
