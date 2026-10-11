<?php

namespace App\Actions\Projects;

use App\Data\Projects\ProjectData;
use App\Data\Projects\UpdateProjectData;
use App\Enums\MembershipType;
use App\Enums\ProjectStatus;
use App\Models\Member;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateProject
{
    public function __invoke(Project $project, UpdateProjectData $data): ProjectData
    {
        Gate::authorize('update', $project);

        return DB::transaction(function () use ($project, $data): ProjectData {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            // Optional fields are omitted when absent but remain present when an
            // explicit null is supplied, allowing nullable Project metadata to
            // be cleared without overwriting unrelated fields.
            $attributes = $data->toArray();

            $this->validateStatusTransition($project, $attributes['status'] ?? null);
            $this->validateMemberTypes(
                array_key_exists('member_id', $attributes) ? $attributes['member_id'] : $project->member_id,
                array_key_exists('venue_id', $attributes) ? $attributes['venue_id'] : $project->venue_id,
                array_key_exists('owned_by', $attributes) ? $attributes['owned_by'] : $project->owned_by,
            );

            if (isset($attributes['status'])) {
                $attributes['status'] = ProjectStatus::from($attributes['status']);
            }

            $project->update($attributes);

            return ProjectData::fromModel($project->refresh());
        });
    }

    private function validateStatusTransition(Project $project, ?string $status): void
    {
        if ($status === null || $status === $project->status->value) {
            return;
        }

        if (! $project->status->canTransitionTo(ProjectStatus::from($status))) {
            throw ValidationException::withMessages(['status' => 'This project status transition is not allowed.']);
        }
    }

    private function validateMemberTypes(?int $memberId, ?int $venueId, ?int $ownerId): void
    {
        $members = Member::query()->whereIn('id', array_values(array_filter([$memberId, $venueId, $ownerId])))->get()->keyBy('id');

        if ($memberId !== null && $members->get($memberId)?->membership_type !== MembershipType::Organisation) {
            throw ValidationException::withMessages(['member_id' => 'The customer must be an organisation.']);
        }

        if ($venueId !== null && $members->get($venueId)?->membership_type !== MembershipType::Venue) {
            throw ValidationException::withMessages(['venue_id' => 'The venue must be a venue member.']);
        }

        if ($ownerId !== null && $members->get($ownerId)?->membership_type !== MembershipType::User) {
            throw ValidationException::withMessages(['owned_by' => 'The owner must be a user member.']);
        }
    }
}
