<?php

namespace App\Actions\Containers;

use App\Concerns\LocksModelsForMutation;
use App\Data\Containers\ContainerData;
use App\Data\Containers\UnsealContainerData;
use App\Enums\ContainerStatus;
use App\Enums\ContainerUnsealReason;
use App\Events\Containers\ContainerUnsealed;
use App\Models\Container;
use App\Services\Containers\ContainerEventRecorder;
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
class UnsealContainer
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
