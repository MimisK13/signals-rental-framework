<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Actions;

use App\Domain\Availability\Shortages\Actions\Concerns\TransitionsShortageResolution;
use App\Domain\Availability\Shortages\Data\ShortageResolutionData;
use App\Domain\Availability\Shortages\Enums\ShortageResolutionStatus;
use App\Domain\Availability\Shortages\Models\ShortageResolution;
use App\Domain\Availability\Shortages\Services\ShortageEventRecorder;
use Illuminate\Support\Facades\Gate;

/**
 * Moves a confirmed resolution into active fulfilment
 * (shortage-resolution-sub-hires.md §8.3: confirmed → in_progress).
 *
 * Signals that fulfilment has begun (e.g. stock is in transit from a supplier),
 * then emits `shortage.resolution.in_progress`.
 */
class StartShortageResolution
{
    use TransitionsShortageResolution;

    public function __construct(
        private readonly ShortageEventRecorder $events,
    ) {}

    public function __invoke(ShortageResolution $resolution): ShortageResolutionData
    {
        Gate::authorize('shortages.resolve');

        $this->guardTransition($resolution, ShortageResolutionStatus::InProgress);

        $resolution->forceFill([
            'status' => ShortageResolutionStatus::InProgress,
        ])->save();

        $this->events->resolutionInProgress($resolution);

        return ShortageResolutionData::fromModel($resolution->load('items'));
    }
}
