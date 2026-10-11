<?php

namespace App\Actions\Projects;

use App\Concerns\CommitsVerbsEvents;
use App\Models\Opportunity;
use App\Models\Project;
use App\Verbs\Events\Opportunities\OpportunityProjectAssigned;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MoveOpportunityToProject
{
    use CommitsVerbsEvents;

    public function __invoke(Project $destination, Opportunity $opportunity): void
    {
        Gate::authorize('update', $destination);
        Gate::authorize('update', $opportunity);

        $this->commitVerbs(function () use ($destination, $opportunity): void {
            $locked = Opportunity::query()->lockForUpdate()->findOrFail($opportunity->id);

            if ($locked->project_id === null) {
                throw ValidationException::withMessages([
                    'opportunity_id' => 'An unassigned opportunity must be attached first.',
                ]);
            }

            if ($locked->project_id === $destination->id) {
                return;
            }

            $source = Project::query()->findOrFail($locked->project_id);
            Gate::authorize('update', $source);

            OpportunityProjectAssigned::fire(
                opportunity_id: $locked->state_id,
                project_id: $destination->id,
            );
        });
    }
}
