<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Data\Registry\CreateCrewMemberData;
use App\Domain\Crew\Data\Registry\CrewMemberData;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewMemberValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateCrewMember
{
    public function __construct(private CrewMemberValidation $validation) {}

    public function __invoke(CreateCrewMemberData $data): CrewMemberData
    {
        Gate::authorize('create', CrewMember::class);

        return DB::transaction(function () use ($data): CrewMemberData {
            $attributes = $this->validation->validate($data->toArray());
            $member = CrewMember::create($attributes);
            CrewAudit::record('crew.member.created', $member);

            return CrewMemberData::fromModel($member->refresh());
        });
    }
}
