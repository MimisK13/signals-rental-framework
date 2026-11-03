<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Policies;

use App\Domain\Administration\Models\User;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Stores\Policies\Concerns\ChecksStoreAccess;
use App\Policies\Traits\AuthorizesByPermission;

class OpportunityPolicy
{
    use AuthorizesByPermission, ChecksStoreAccess;

    public function view(User $user, ?Opportunity $opportunity = null): bool
    {
        return $user->can($this->viewPermission())
            && ($opportunity === null || $this->canAccessStore($user, $opportunity));
    }

    public function update(User $user, ?Opportunity $opportunity = null): bool
    {
        return $user->can($this->editPermission())
            && ($opportunity === null || $this->canAccessStore($user, $opportunity));
    }

    protected function viewPermission(): string
    {
        return 'opportunities.view';
    }

    protected function managePermission(): string
    {
        return 'opportunities.create';
    }

    protected function createPermission(): string
    {
        return 'opportunities.create';
    }

    protected function editPermission(): string
    {
        return 'opportunities.edit';
    }

    protected function deletePermission(): string
    {
        return 'opportunities.delete';
    }
}
