<?php

declare(strict_types=1);

namespace App\Domain\Crew\Policies;

use App\Domain\Crew\Models\CrewMember;
use App\Models\User;

class CrewMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crew.view');
    }

    public function view(User $user, CrewMember $crewMember): bool
    {
        return $user->can('crew.view');
    }

    public function viewSensitive(User $user, CrewMember $crewMember): bool
    {
        return $user->can('crew.view.sensitive');
    }

    public function create(User $user): bool
    {
        return $user->can('crew.create');
    }

    public function update(User $user, CrewMember $crewMember): bool
    {
        return $user->can('crew.edit');
    }

    /**
     * "Delete" archives the member; records stay schedulable in history.
     */
    public function delete(User $user, CrewMember $crewMember): bool
    {
        return $user->can('crew.delete');
    }

    public function restore(User $user, CrewMember $crewMember): bool
    {
        return $user->can('crew.delete');
    }

    public function forceDelete(User $user, CrewMember $crewMember): bool
    {
        return $user->can('crew.delete');
    }
}
