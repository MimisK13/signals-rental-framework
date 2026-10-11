<?php

namespace App\Actions\Projects;

use App\Data\Projects\CreateProjectData;
use App\Data\Projects\ProjectData;
use App\Enums\MembershipType;
use App\Enums\ProjectStatus;
use App\Models\Member;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateProject
{
    public function __invoke(CreateProjectData $data): ProjectData
    {
        Gate::authorize('create', Project::class);
        $this->validateMemberTypes($data->member_id, $data->venue_id, $data->owned_by);

        return DB::transaction(function () use ($data): ProjectData {
            $project = Project::create([
                'name' => $data->name,
                'member_id' => $data->member_id,
                'venue_id' => $data->venue_id,
                'owned_by' => $data->owned_by,
                'status' => ProjectStatus::from($data->status),
                'starts_at' => $data->starts_at,
                'ends_at' => $data->ends_at,
            ]);

            return ProjectData::fromModel($project);
        });
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
