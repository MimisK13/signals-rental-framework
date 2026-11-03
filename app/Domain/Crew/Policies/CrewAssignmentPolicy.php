<?php

declare(strict_types=1);

namespace App\Domain\Crew\Policies;

use App\Domain\Administration\Models\User;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Policies\Concerns\ChecksStoreAccess;

class CrewAssignmentPolicy
{
    use ChecksStoreAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('crew.assignments.view');
    }

    public function view(User $user, CrewAssignment $assignment): bool
    {
        return $user->can('crew.assignments.view')
            && $this->canAccessStore($user, $assignment->store_id);
    }

    public function create(User $user): bool
    {
        return $user->can('crew.assignments.create');
    }

    public function confirm(User $user, CrewAssignment $assignment): bool
    {
        return $user->can('crew.assignments.confirm')
            && $this->canAccessStore($user, $assignment->store_id)
            && in_array($assignment->status, [CrewAssignmentStatus::Proposed, CrewAssignmentStatus::Invited], true);
    }

    public function reject(User $user, CrewAssignment $assignment): bool
    {
        return $user->can('crew.assignments.confirm')
            && $this->canAccessStore($user, $assignment->store_id)
            && $assignment->status === CrewAssignmentStatus::Invited;
    }

    public function cancel(User $user, CrewAssignment $assignment): bool
    {
        return $user->can('crew.assignments.cancel')
            && $this->canAccessStore($user, $assignment->store_id)
            && in_array($assignment->status, [
                CrewAssignmentStatus::Proposed,
                CrewAssignmentStatus::Invited,
                CrewAssignmentStatus::Confirmed,
            ], true);
    }

    public function substitute(User $user, CrewAssignment $assignment): bool
    {
        return $user->can('crew.assignments.substitute')
            && $this->canAccessStore($user, $assignment->store_id)
            && $assignment->status === CrewAssignmentStatus::Confirmed;
    }

    /**
     * Replacing a cancelled, rejected, or substituted occupant of a line.
     */
    public function reassign(User $user, CrewAssignment $assignment): bool
    {
        return $user->can('crew.assignments.create')
            && $this->canAccessStore($user, $assignment->store_id)
            && in_array($assignment->status, [
                CrewAssignmentStatus::Rejected,
                CrewAssignmentStatus::Cancelled,
                CrewAssignmentStatus::Substituted,
            ], true);
    }

    /**
     * Confirming through a hard availability or double-booking conflict.
     */
    public function overrideConflicts(User $user): bool
    {
        return $user->can('crew.conflicts.override');
    }
}
