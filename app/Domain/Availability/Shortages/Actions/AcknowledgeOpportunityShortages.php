<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Actions;

use App\Domain\Availability\Shortages\Data\AcknowledgeShortageData;
use App\Domain\Availability\Shortages\Models\ShortageAcknowledgement;
use App\Domain\Availability\Shortages\Services\ShortageConfirmationGate;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Explicitly acknowledges the shortages on an opportunity, recording the gate
 * acknowledgement with a frozen snapshot (shortage-resolution-sub-hires.md §7.3).
 *
 * The manual counterpart to the confirmation gate's implicit Warn acknowledgement
 * — used when an operator wants to record awareness ahead of conversion. Rejects
 * acknowledging when there is nothing to acknowledge.
 */
class AcknowledgeOpportunityShortages
{
    public function __construct(
        private readonly ShortageConfirmationGate $gate,
    ) {}

    public function __invoke(Opportunity $opportunity, AcknowledgeShortageData $data): ShortageAcknowledgement
    {
        Gate::authorize('shortages.resolve');

        $result = $this->gate->evaluate($opportunity);

        if ($result->shortages->isEmpty()) {
            throw ValidationException::withMessages([
                'shortages' => ['This opportunity has no shortages to acknowledge.'],
            ]);
        }

        return $this->gate->recordAcknowledgement($opportunity, $result, $data->notes);
    }
}
