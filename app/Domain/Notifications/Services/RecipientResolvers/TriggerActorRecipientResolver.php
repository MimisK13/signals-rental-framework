<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services\RecipientResolvers;

use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Contracts\RecipientResolver;
use App\Domain\Notifications\Data\NotificationContextData;
use Illuminate\Support\Collection;

/**
 * Resolves the member who triggered the notification event.
 */
class TriggerActorRecipientResolver implements RecipientResolver
{
    public function type(): string
    {
        return 'trigger_actor';
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return Collection<int, Member>
     */
    public function resolve(array $rule, NotificationContextData $context): Collection
    {
        $actor = $context->actor;

        return $actor !== null
            ? Member::query()->whereKey($actor->id)->get()
            : collect();
    }
}
