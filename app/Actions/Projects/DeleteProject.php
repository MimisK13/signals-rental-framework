<?php

namespace App\Actions\Projects;

use App\Concerns\CommitsVerbsEvents;
use App\Models\Opportunity;
use App\Models\Project;
use App\Verbs\Events\Opportunities\OpportunityProjectAssigned;
use Illuminate\Support\Facades\Gate;

class DeleteProject
{
    use CommitsVerbsEvents;

    public function __invoke(Project $project): void
    {
        Gate::authorize('delete', $project);

        $this->commitVerbs(function () use ($project): void {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $opportunities = Opportunity::query()
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            foreach ($opportunities as $opportunity) {
                OpportunityProjectAssigned::fire(
                    opportunity_id: $opportunity->state_id,
                    project_id: null,
                );
            }

            $project->delete();
        });
    }
}
