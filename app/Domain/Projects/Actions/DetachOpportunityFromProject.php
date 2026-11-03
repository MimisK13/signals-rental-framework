<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Verbs\Events\OpportunityProjectAssigned;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DetachOpportunityFromProject
{
    use CommitsVerbsEvents;

    public function __invoke(Project $project, Opportunity $opportunity): void
    {
        Gate::authorize('update', $project);
        Gate::authorize('update', $opportunity);

        $this->commitVerbs(function () use ($project, $opportunity): void {
            $locked = Opportunity::query()->lockForUpdate()->findOrFail($opportunity->id);

            if ($locked->project_id === null) {
                return;
            }

            if ($locked->project_id !== $project->id) {
                throw ValidationException::withMessages([
                    'opportunity_id' => 'This opportunity belongs to another project.',
                ]);
            }

            OpportunityProjectAssigned::fire(
                opportunity_id: $locked->state_id,
                project_id: null,
            );
        });
    }
}
