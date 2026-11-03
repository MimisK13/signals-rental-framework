<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Domain\Administration\Models\User;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Contracts\RecipientResolver;
use App\Domain\Notifications\Data\NotificationContextData;
use Illuminate\Support\Collection;

final class CrewCoordinatorRecipientResolver implements RecipientResolver
{
    public function type(): string
    {
        return 'crew_coordinators';
    }

    /** @param array<string,mixed> $rule @return Collection<int,Member> */
    public function resolve(array $rule, NotificationContextData $context): Collection
    {
        $permission = match (true) {
            str_starts_with($context->type->key, 'crew.timesheet.') => 'crew.timesheets.approve',
            str_starts_with($context->type->key, 'crew.expense.') => 'crew.expenses.approve',
            str_starts_with($context->type->key, 'crew.availability.') => 'crew.availability.approve',
            default => 'crew.requests.view',
        };
        $storeId = $context->subject instanceof CrewRequestLine ? $context->subject->request?->store_id : $context->subject->getAttribute('store_id');
        $ids = User::permission($permission)->with('member')->get()->filter(function (User $user) use ($storeId): bool {
            return $storeId === null || $user->canAccessStore((int) $storeId);
        })->pluck('member_id')->filter()->all();

        return Member::query()->whereIn('id', $ids)->get();
    }
}
