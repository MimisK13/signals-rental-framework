<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityVersionData;
use App\Domain\Projects\Opportunities\Models\OpportunityVersion;
use App\Domain\Projects\Opportunities\Verbs\Events\VersionDeclined;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Marks a quote version as Declined by the customer (opportunity-lifecycle.md
 * §8.6).
 */
class DeclineVersion
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityVersion $version, ?string $reason = null): OpportunityVersionData
    {
        Gate::authorize('opportunities.edit');

        $this->commitVerbs(function () use ($version, $reason): void {
            VersionDeclined::fire(
                version_id: $version->state_id,
                reason: $reason,
                declined_at: CarbonImmutable::now()->toIso8601String(),
            );
        });

        return OpportunityVersionData::fromModel($version->refresh());
    }
}
