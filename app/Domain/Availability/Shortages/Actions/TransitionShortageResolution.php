<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Actions;

use App\Domain\Availability\Shortages\Data\ApplyShortageResolutionTransitionData;
use App\Domain\Availability\Shortages\Data\ShortageResolutionData;
use App\Domain\Availability\Shortages\Data\TransitionShortageResolutionData;
use App\Domain\Availability\Shortages\Enums\ShortageResolutionStatus;
use App\Domain\Availability\Shortages\Models\ShortageResolution;
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
