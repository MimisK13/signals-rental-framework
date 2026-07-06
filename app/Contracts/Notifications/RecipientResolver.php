<?php

namespace App\Contracts\Notifications;

use App\Data\Notifications\NotificationContextData;
use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * Resolves notification recipients from a recipient rule definition (P24).
 */
interface RecipientResolver
{
    /**
     * The recipient rule type key (e.g. subject_owner, role).
     */
    public function type(): string;

    /**
     * @param  array<string, mixed>  $rule
     * @return Collection<int, Member>
     */
    public function resolve(array $rule, NotificationContextData $context): Collection;
}
