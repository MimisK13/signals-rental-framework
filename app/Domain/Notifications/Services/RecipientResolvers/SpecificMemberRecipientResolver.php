<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services\RecipientResolvers;

use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Contracts\RecipientResolver;
use App\Domain\Notifications\Data\NotificationContextData;
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
