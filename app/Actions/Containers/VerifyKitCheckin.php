<?php

namespace App\Actions\Containers;

use App\Data\Containers\ContainerData;
use App\Models\Container;
use App\Services\Containers\ContainerEventRecorder;
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

        if ($container->checkin_verification_required_at === null) {
            throw ValidationException::withMessages([
                'container' => __('This kit does not require check-in verification.'),
            ]);
        }

        $container->forceFill([
            'checkin_verification_required_at' => null,
        ])->saveQuietly();

        app(ContainerEventRecorder::class)->checkinVerified($container->fresh() ?? $container);

        return ContainerData::fromModel($container->fresh() ?? $container);
    }
}
