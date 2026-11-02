<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Actions;

use App\Concerns\LocksModelsForMutation;
use App\Domain\Warehouse\Containers\Data\ContainerData;
use App\Domain\Warehouse\Containers\Data\UnsealContainerData;
use App\Domain\Warehouse\Containers\Enums\ContainerStatus;
use App\Domain\Warehouse\Containers\Enums\ContainerUnsealReason;
use App\Domain\Warehouse\Containers\Events\ContainerUnsealed;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Services\ContainerEventRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Reopen a sealed container (serialised-containers.md §"Container Lifecycle").
 *
 * Unsealing does NOT cascade to nested containers — only the target container
 * returns to open. Nested containers retain their own seal state.
 */
final class UnsealContainer
{
    use LocksModelsForMutation;

    public function __invoke(Container $container, UnsealContainerData $data): ContainerData
    {
        Gate::authorize('unseal', $container);

        if ($container->status !== ContainerStatus::Sealed) {
            throw ValidationException::withMessages([
                'container' => __('Only sealed containers can be unsealed.'),
            ]);
        }

        $reason = ContainerUnsealReason::from($data->reason);

        return DB::transaction(function () use ($container, $reason): ContainerData {
            $locked = $this->lockModelForMutation($container);

            if ($locked->status !== ContainerStatus::Sealed) {
                throw ValidationException::withMessages([
                    'container' => __('Only sealed containers can be unsealed.'),
                ]);
            }

            $locked->update([
                'status' => ContainerStatus::Open,
                'unsealed_at' => Carbon::now('UTC'),
                'unsealed_by_user_id' => Auth::id(),
            ]);

            $locked->refresh();

            ContainerUnsealed::dispatch($locked, $reason);
            app(ContainerEventRecorder::class)->unsealed($locked, $reason);

            return ContainerData::fromModel($locked);
        });
    }
}
