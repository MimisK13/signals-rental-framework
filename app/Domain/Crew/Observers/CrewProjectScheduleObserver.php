<?php

declare(strict_types=1);

namespace App\Domain\Crew\Observers;

use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Projects\Models\Project;
use App\Models\Scopes\StoreScope;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\DB;

final class CrewProjectScheduleObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(Project $project): void
    {
        if (! $project->wasChanged(['starts_at', 'ends_at'])) {
            return;
        }

        CrewAssignment::withoutGlobalScope(StoreScope::class)
            ->where('project_id', $project->id)->where('status', 'confirmed')
            ->select(['id', 'crew_member_id'])->orderBy('id')
            ->chunkById(100, function ($assignments): void {
                foreach ($assignments as $candidate) {
                    DB::transaction(function () use ($candidate): void {

                        CrewMember::withTrashed()
                            ->whereKey($candidate->crew_member_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $assignment = CrewAssignment::withoutGlobalScope(StoreScope::class)
                            ->whereKey($candidate->id)
                            ->where('status', 'confirmed')
                            ->lockForUpdate()
                            ->first();

                        if ($assignment === null) {
                            return;
                        }

                        $assignment->update([
                            'requires_realignment' => true,
                            'realignment_flagged_at' => now(),
                            'realignment_reason' => 'Project schedule changed. Review and explicitly realign shifts.',
                        ]);

                        CrewAudit::record('crew.assignment.realignment_required', $assignment);
                    });
                }
            });
    }
}
