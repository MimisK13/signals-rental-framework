<?php

namespace App\Actions\Shortages;

use App\Data\Shortages\ApplyShortageResolutionTransitionData;
use App\Data\Shortages\ShortageResolutionData;
use App\Data\Shortages\TransitionShortageResolutionData;
use App\Enums\ShortageResolutionStatus;
use App\Models\ShortageResolution;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Unified shortage-resolution status transition (shortage-resolution-sub-hires.md §8.3).
 *
 * Validates the move against {@see ShortageResolutionStatus::canTransitionTo()}, then
 * delegates to the matching lifecycle action so audit/events stay consistent with
 * the existing PATCH transition endpoints.
 */
class TransitionShortageResolution
{
    public function __construct(
        private readonly ConfirmShortageResolution $confirm,
        private readonly StartShortageResolution $start,
        private readonly FulfillShortageResolution $fulfill,
        private readonly PartiallyFulfillShortageResolution $partiallyFulfill,
        private readonly CancelShortageResolution $cancel,
        private readonly FailShortageResolution $fail,
    ) {}

    public function __invoke(ShortageResolution $resolution, ApplyShortageResolutionTransitionData $data): ShortageResolutionData
    {
        Gate::authorize('shortages.resolve');

        $target = $data->targetStatus();

        return match ($target) {
            ShortageResolutionStatus::Confirmed => ($this->confirm)($resolution),
            ShortageResolutionStatus::InProgress => ($this->start)($resolution),
            ShortageResolutionStatus::Fulfilled => ($this->fulfill)($resolution),
            ShortageResolutionStatus::PartiallyFulfilled => ($this->partiallyFulfill)($resolution),
            ShortageResolutionStatus::Cancelled => ($this->cancel)($resolution, TransitionShortageResolutionData::from([
                'reason' => $data->reason,
            ])),
            ShortageResolutionStatus::Failed => ($this->fail)($resolution, TransitionShortageResolutionData::from([
                'reason' => $data->reason,
            ])),
            default => throw ValidationException::withMessages([
                'status' => ["Status {$target->label()} cannot be applied via transition."],
            ]),
        };
    }
}
