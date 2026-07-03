<?php

namespace App\Services\Notifications\RecipientResolvers;

use App\Contracts\Notifications\RecipientResolver;
use App\Data\Notifications\NotificationContextData;
use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * Resolves a single member by ID from the recipient rule.
 */
class SpecificMemberRecipientResolver implements RecipientResolver
{
    public function type(): string
    {
        return 'specific_member';
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return Collection<int, Member>
     */
    public function resolve(array $rule, NotificationContextData $context): Collection
    {
        $memberId = $rule['member_id'] ?? null;

        if (! is_int($memberId) && ! is_numeric($memberId)) {
            return collect();
        }

        return Member::query()->whereKey((int) $memberId)->get();
    }
}
