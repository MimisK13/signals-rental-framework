<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Policies;

use App\Domain\Administration\Models\User;
use App\Domain\Projects\Opportunities\Models\OpportunityCost;

/**
 * Opportunity costs are visible to anyone who can view the parent opportunity.
 * Monetary fields are stripped at the DTO serialisation layer unless the user
 * also holds `costs.view` (field-level visibility per the permissions plan).
 */
class OpportunityCostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('opportunities.view');
    }

    public function view(User $user, OpportunityCost $cost): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Whether the user may see monetary values on a cost row (not just metadata).
     */
    public function viewAmounts(User $user, OpportunityCost $cost): bool
    {
        return $user->can('costs.view') && $this->view($user, $cost);
    }

    public function create(User $user): bool
    {
        return $user->can('opportunities.edit');
    }

    public function update(User $user, OpportunityCost $cost): bool
    {
        return $user->can('opportunities.edit');
    }

    public function delete(User $user, OpportunityCost $cost): bool
    {
        return $user->can('opportunities.edit');
    }
}
