<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services\RecipientResolvers;

use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Contracts\RecipientResolver;
use App\Domain\Notifications\Data\NotificationContextData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use Illuminate\Support\Collection;

/**
 * Resolves the customer contact (member_id) from the notification subject.
 */
class ContactRecipientResolver implements RecipientResolver
{
    public function type(): string
    {
        return 'contact';
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return Collection<int, Member>
     */
    public function resolve(array $rule, NotificationContextData $context): Collection
    {
        $subject = $context->subject;

        $memberId = match (true) {
            $subject instanceof Opportunity => $subject->member_id,
            isset($subject->member_id) => $subject->member_id,
            default => null,
        };

        if ($memberId === null) {
            return collect();
        }

        return Member::query()->whereKey($memberId)->get();
    }
}
