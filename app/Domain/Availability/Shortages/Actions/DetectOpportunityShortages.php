<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Actions;

use App\Domain\Availability\Shortages\Services\ShortageDetector;
use App\Domain\Availability\Shortages\ValueObjects\ShortageCollection;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Gate;

/**
 * Detects the shortages on an opportunity for the read API / badge endpoint
 * (shortage-resolution-sub-hires.md §2.4). Authorises, then delegates to the
 * {@see ShortageDetector}; shortages are computed, so nothing is persisted.
 */
class DetectOpportunityShortages
{
    public function __construct(
        private readonly ShortageDetector $detector,
    ) {}

    public function __invoke(Opportunity $opportunity): ShortageCollection
    {
        Gate::authorize('shortages.view');

        return $this->detector->forOpportunity($opportunity);
    }
}
