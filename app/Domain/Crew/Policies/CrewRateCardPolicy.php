<?php

declare(strict_types=1);

namespace App\Domain\Crew\Policies;

use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Policies\Concerns\ChecksStoreAccess;
use App\Models\User;

/**
 * Rate cards are deliberately separate from member editing: compensation is
 * confidential and requires its own permission.
 */
class CrewRateCardPolicy
{
    use ChecksStoreAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('crew.rates.view');
    }

    public function view(User $user, CrewRateCard $rateCard): bool
    {
        return $user->can('crew.rates.view')
            && $this->canAccessOptionalStore($user, $rateCard->store_id);
    }

    public function create(User $user): bool
    {
        return $user->can('crew.rates.manage');
    }

    public function update(User $user, CrewRateCard $rateCard): bool
    {
        return $user->can('crew.rates.manage')
            && $this->canAccessOptionalStore($user, $rateCard->store_id);
    }

    public function delete(User $user, CrewRateCard $rateCard): bool
    {
        return $user->can('crew.rates.manage')
            && $this->canAccessOptionalStore($user, $rateCard->store_id);
    }

    public function restore(User $user, CrewRateCard $rateCard): bool
    {
        return $this->delete($user, $rateCard);
    }
}
