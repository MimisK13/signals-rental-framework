<?php

namespace App\Actions\Containers;

use App\Concerns\LocksModelsForMutation;
use App\Data\Containers\ContainerData;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use App\Events\Containers\BeforeContainerSealed;
use App\Events\Containers\ContainerSealed;
use App\Models\Container;
use App\Services\Containers\ContainerEventRecorder;
use App\Services\Containers\ContainerManifestService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Seal an open container — the QA gate before dispatch (serialised-containers.md
 * §"Sealing Rules").
 *
 * Guards: open status; ≥1 packed item; strict {@see ContainerScanMode::Strict}
 * template match (per-product 422 details); all nested containers sealed.
 * Dispatches {@see BeforeContainerSealed} (plugins may veto) then records
 * `container.sealed` via {@see ContainerEventRecorder}.
 */
class SealContainer
{
    use LocksModelsForMutation;

    public function __invoke(Container $container): ContainerData
    {
        Gate::authorize('seal', $container);

        if ($container->status !== ContainerStatus::Open) {
            throw ValidationException::withMessages([
                'container' => __('Only open containers can be sealed.'),
            ]);
        }

        if ($container->activeItems()->count() < 1) {
            throw ValidationException::withMessages([
                'container' => __('A container must contain at least one item to be sealed.'),
            ]);
        }

        app(ContainerManifestService::class)->guardNestedContainersSealed($container);

        if ($container->scan_mode === ContainerScanMode::Strict) {
            $violations = app(ContainerManifestService::class)->strictTemplateViolations($container);

            if ($violations !== []) {
                throw ValidationException::withMessages($violations);
            }
        }

        $result = DB::transaction(function () use ($container): ContainerData {
            $locked = $this->lockModelForMutation($container);

            if ($locked->status !== ContainerStatus::Open) {
                throw ValidationException::withMessages([
                    'container' => __('Only open containers can be sealed.'),
                ]);
            }

            if ($locked->activeItems()->count() < 1) {
                throw ValidationException::withMessages([
                    'container' => __('A container must contain at least one item to be sealed.'),
                ]);
            }

            app(ContainerManifestService::class)->guardNestedContainersSealed($locked);

            if ($locked->scan_mode === ContainerScanMode::Strict) {
                $violations = app(ContainerManifestService::class)->strictTemplateViolations($locked);

                if ($violations !== []) {
                    throw ValidationException::withMessages($violations);
                }
            }

            BeforeContainerSealed::dispatch($locked);

            $itemCount = $locked->activeItems()->count();

            $locked->update([
                'status' => ContainerStatus::Sealed,
                'sealed_at' => Carbon::now('UTC'),
                'sealed_by_user_id' => Auth::id(),
            ]);

            $locked->refresh();

            ContainerSealed::dispatch($locked, $itemCount);
            app(ContainerEventRecorder::class)->sealed($locked, $itemCount);

            return ContainerData::fromModel($locked);
        });

        $container->refresh();

        return $result;
    }
}
