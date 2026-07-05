<?php

namespace App\Actions\Shortages;

use App\Actions\Shortages\Concerns\TransitionsShortageResolution;
use App\Data\Shortages\ShortageResolutionData;
use App\Enums\ShortageResolutionStatus;
use App\Models\ShortageResolution;
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
