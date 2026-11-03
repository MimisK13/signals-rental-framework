<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Data\NotificationContextData;
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
