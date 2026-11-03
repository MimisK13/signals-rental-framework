<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Actions;

use App\Domain\Availability\Shortages\Actions\Concerns\TransitionsShortageResolution;
use App\Domain\Availability\Shortages\Data\ShortageResolutionData;
use App\Domain\Availability\Shortages\Enums\ShortageResolutionStatus;
use App\Domain\Availability\Shortages\Models\ShortageResolution;
use App\Domain\Availability\Shortages\Services\ShortageEventRecorder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Completes a resolution (shortage-resolution-sub-hires.md §8.3:
 * in_progress → fulfilled, or partially_fulfilled → fulfilled).
 *
 * The resolution's stock is now available; stamps the fulfilment time and emits
 * `shortage.resolution.fulfilled`. Consumers (availability engine, shortage
 * detector) re-evaluate off this event.
 */
class FulfillShortageResolution
{
    use TransitionsShortageResolution;

    public function __construct(
        private readonly ShortageEventRecorder $events,
    ) {}

    public function __invoke(ShortageResolution $resolution): ShortageResolutionData
    {
        Gate::authorize('shortages.resolve');

        $this->guardTransition($resolution, ShortageResolutionStatus::Fulfilled);

        $resolution->forceFill([
            'status' => ShortageResolutionStatus::Fulfilled,
            'fulfilled_at' => Carbon::now('UTC'),
        ])->save();

        $this->events->resolutionFulfilled($resolution);

        return ShortageResolutionData::fromModel($resolution->load('items'));
    }
}
