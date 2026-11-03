<?php

declare(strict_types=1);

namespace App\Domain\Crew\Policies;

use App\Domain\Administration\Models\User;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Policies\Concerns\ChecksStoreAccess;

class CrewShiftPolicy
{
    use ChecksStoreAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('crew.shifts.view');
    }

    public function view(User $user, CrewShift $shift): bool
    {
        return $user->can('crew.shifts.view')
            && $this->canAccessStore($user, $shift->store_id);
    }

    public function create(User $user): bool
    {
        return $user->can('crew.shifts.manage');
    }

    public function update(User $user, CrewShift $shift): bool
    {
        return $user->can('crew.shifts.manage')
            && $this->canAccessStore($user, $shift->store_id)
            && ! in_array($shift->status, [CrewShiftStatus::Completed, CrewShiftStatus::Cancelled], true);
    }

    /**
     * Only unstarted shifts may be deleted; anything worked must be cancelled or
     * corrected instead so the audit trail survives.
     */
    public function delete(User $user, CrewShift $shift): bool
    {
        return $user->can('crew.shifts.manage')
            && $this->canAccessStore($user, $shift->store_id)
            && in_array($shift->status, [CrewShiftStatus::Planned, CrewShiftStatus::Cancelled], true);
    }

    public function confirm(User $user, CrewShift $shift): bool
    {
        return $user->can('crew.shifts.manage')
            && $this->canAccessStore($user, $shift->store_id)
            && $shift->status === CrewShiftStatus::Planned;
    }

    public function start(User $user, CrewShift $shift): bool
    {
        return $user->can('crew.shifts.manage')
            && $this->canAccessStore($user, $shift->store_id)
            && $shift->status === CrewShiftStatus::Confirmed;
    }

    public function complete(User $user, CrewShift $shift): bool
    {
        return $user->can('crew.shifts.manage')
            && $this->canAccessStore($user, $shift->store_id)
            && in_array($shift->status, [CrewShiftStatus::Confirmed, CrewShiftStatus::InProgress], true);
    }
}
