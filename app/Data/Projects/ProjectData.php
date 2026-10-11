<?php

namespace App\Data\Projects;

use App\Models\Project;
use Spatie\LaravelData\Data;

class ProjectData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $member_id,
        public ?int $venue_id,
        public ?int $owned_by,
        public string $status,
        public ?string $starts_at,
        public ?string $ends_at,
        public int $opportunities_count = 0,
    ) {}

    public static function fromModel(Project $project): self
    {
        return new self(
            id: $project->id,
            name: $project->name,
            member_id: $project->member_id,
            venue_id: $project->venue_id,
            owned_by: $project->owned_by,
            status: $project->status->value,
            starts_at: $project->starts_at?->toIso8601String(),
            ends_at: $project->ends_at?->toIso8601String(),
            opportunities_count: (int) ($project->opportunities_count ?? $project->opportunities()->count()),
        );
    }
}
