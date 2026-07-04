<?php

namespace App\Actions\Containers;

use App\Data\Containers\ContainerData;
use App\Enums\ContainerDissolveReason;
use App\Enums\ContainerStatus;
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
            $itemCount = app(ContainerDissolveService::class)->dissolveTree(
                $container,
                ContainerDissolveReason::Manual,
            );

            app(ContainerEventRecorder::class)->dissolved(
                $container->fresh() ?? $container,
                ContainerDissolveReason::Manual,
                $itemCount,
            );

            return ContainerData::fromModel($container->fresh() ?? $container);
        });
    }
}
