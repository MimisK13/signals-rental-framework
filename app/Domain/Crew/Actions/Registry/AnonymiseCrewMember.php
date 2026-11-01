<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Registry;

use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Services\CrewMemberAnonymizer;
use Illuminate\Support\Facades\Gate;

final class AnonymiseCrewMember
{
    public function __construct(private CrewMemberAnonymizer $anonymizer) {}

    public function __invoke(CrewMember $member): void
    {
        Gate::authorize('delete', $member);
        $this->anonymizer->anonymise($member);
    }
}
