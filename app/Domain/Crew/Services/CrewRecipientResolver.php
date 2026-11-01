<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Contracts\Notifications\RecipientResolver;
use App\Data\Notifications\NotificationContextData;
use App\Domain\Crew\Models\CrewMember;
use App\Models\Member;
use Illuminate\Support\Collection;

final class CrewRecipientResolver implements RecipientResolver
{
    public function type(): string
    {
        return 'crew_member';
    }

    /** @param array<string,mixed> $rule @return Collection<int,Member> */
    public function resolve(array $rule, NotificationContextData $context): Collection
    {
        $subject = $context->subject;
        $crewMember = $subject instanceof CrewMember ? $subject : CrewMember::withTrashed()->find($subject->getAttribute('crew_member_id'));
        if ($crewMember === null) {
            return collect();
        }
        $identity = $crewMember->user()->value('member_id') ?? $crewMember->member_id;

        return $identity === null ? collect() : Member::query()->whereKey($identity)->get();
    }
}
