<?php

namespace App\Services\Notifications\RecipientResolvers;

use App\Contracts\Notifications\RecipientResolver;
use App\Data\Notifications\NotificationContextData;
use App\Models\Member;
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
