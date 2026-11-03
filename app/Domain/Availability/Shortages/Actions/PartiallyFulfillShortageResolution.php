<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Actions;

use App\Domain\Availability\Shortages\Actions\Concerns\TransitionsShortageResolution;
use App\Domain\Availability\Shortages\Data\ShortageResolutionData;
use App\Domain\Availability\Shortages\Enums\ShortageResolutionStatus;
use App\Domain\Availability\Shortages\Models\ShortageResolution;
use Illuminate\Support\Facades\Gate;

/**
 * Marks an in-progress resolution as partially fulfilled
 * (shortage-resolution-sub-hires.md §8.3: in_progress → partially_fulfilled).
 *
 * Mirrors {@see ReceivePurchaseOrder}'s partial-receipt path: status-only update with
 * no dedicated recorder event (partial fulfilment is an intermediate state).
 */
class PartiallyFulfillShortageResolution
{
    use TransitionsShortageResolution;

    public function __invoke(ShortageResolution $resolution): ShortageResolutionData
    {
        Gate::authorize('shortages.resolve');

        $this->guardTransition($resolution, ShortageResolutionStatus::PartiallyFulfilled);

        $resolution->forceFill([
            'status' => ShortageResolutionStatus::PartiallyFulfilled,
        ])->save();

        return ShortageResolutionData::fromModel($resolution->load('items'));
    }
}
