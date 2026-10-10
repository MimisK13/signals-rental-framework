<?php

namespace App\Actions\Containers;

use App\Data\Containers\ContainerData;
use App\Models\Container;
use App\Services\Containers\ContainerEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Clears the parent-then-verify check-in flag once condition assessment is complete.
 */
class VerifyKitCheckin
{
    public function __invoke(Container $container): ContainerData
    {
        Gate::authorize('verifyCheckin', $container);

        $actorId = Auth::id();
        $occurredAt = CarbonImmutable::now('UTC');

        return DB::transaction(function () use ($container, $actorId, $occurredAt): ContainerData {
            $locked = Container::query()
                ->whereKey($container->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw (new ModelNotFoundException)->setModel(Container::class, [$container->id]);
            }

            if ($locked->checkin_verification_required_at === null) {
                throw ValidationException::withMessages([
                    'container' => __('This kit does not require check-in verification.'),
                ]);
            }

            $locked->forceFill([
                'checkin_verification_required_at' => null,
            ])->saveQuietly();

            app(ContainerEventRecorder::class)->checkinVerified(
                $locked->fresh() ?? $locked,
                $actorId,
                $occurredAt,
                failOnAuditFailure: true,
            );

            return ContainerData::fromModel($locked->fresh() ?? $locked);
        });
    }
}
