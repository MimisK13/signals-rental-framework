<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Verbs\Events\OpportunityProjectAssigned;
use Illuminate\Support\Facades\Gate;

final class DeleteProject
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
