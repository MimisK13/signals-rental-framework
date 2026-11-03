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
 * Confirms a pending (or monitoring) shortage resolution
 * (shortage-resolution-sub-hires.md §8.3: pending → confirmed).
 *
 * Marks the resolution accepted (e.g. a supplier confirmed a sub-hire, or a
 * watched shortage freed up), stamping the confirming user and time, then emits
 * `shortage.resolution.confirmed`.
 */
class ConfirmShortageResolution
{
    use TransitionsShortageResolution;

    public function __construct(
        private readonly ShortageEventRecorder $events,
    ) {}

    public function __invoke(ShortageResolution $resolution): ShortageResolutionData
    {
        Gate::authorize('shortages.resolve');

        $this->guardTransition($resolution, ShortageResolutionStatus::Confirmed);

        $resolution->forceFill([
            'status' => ShortageResolutionStatus::Confirmed,
            'confirmed_by' => auth()->id(),
            'confirmed_at' => Carbon::now('UTC'),
        ])->save();

        $this->events->resolutionConfirmed($resolution);

        return ShortageResolutionData::fromModel($resolution->load('items'));
    }
}
