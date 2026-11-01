<?php

declare(strict_types=1);

namespace App\Domain\Crew\Policies;

use App\Domain\Crew\Enums\CrewTimesheetStatus;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Policies\Concerns\ChecksStoreAccess;
use App\Models\User;

class CrewTimesheetPolicy
{
    use ChecksStoreAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('crew.timesheets.view');
    }

    public function view(User $user, CrewTimesheet $timesheet): bool
    {
        return $user->can('crew.timesheets.view')
            && $this->canAccessStore($user, $timesheet->store_id);
    }

    public function create(User $user): bool
    {
        return $user->can('crew.timesheets.create');
    }

    public function update(User $user, CrewTimesheet $timesheet): bool
    {
        return $user->can('crew.timesheets.create')
            && $this->canAccessStore($user, $timesheet->store_id)
            && in_array($timesheet->status, [CrewTimesheetStatus::Draft, CrewTimesheetStatus::Rejected], true);
    }

    public function submit(User $user, CrewTimesheet $timesheet): bool
    {
        return $user->can('crew.timesheets.create')
            && $this->canAccessStore($user, $timesheet->store_id)
            && in_array($timesheet->status, [CrewTimesheetStatus::Draft, CrewTimesheetStatus::Rejected], true);
    }

    /**
     * Approval freezes the totals and is explicitly not payment. Self-approval is
     * blocked unless the approver also holds the audited `crew.timesheets.lock`
     * break-glass permission.
     */
    public function approve(User $user, CrewTimesheet $timesheet): bool
    {
        return $user->can('crew.timesheets.approve')
            && $this->canAccessStore($user, $timesheet->store_id)
            && $timesheet->status === CrewTimesheetStatus::Submitted
            && (! $this->isOwnRecord($user, $timesheet) || $user->can('crew.timesheets.lock'));
    }

    public function reject(User $user, CrewTimesheet $timesheet): bool
    {
        return $user->can('crew.timesheets.approve')
            && $this->canAccessStore($user, $timesheet->store_id)
            && $timesheet->status === CrewTimesheetStatus::Submitted
            && (! $this->isOwnRecord($user, $timesheet) || $user->can('crew.timesheets.lock'));
    }

    public function lock(User $user, CrewTimesheet $timesheet): bool
    {
        return $user->can('crew.timesheets.lock')
            && $this->canAccessStore($user, $timesheet->store_id)
            && $timesheet->status === CrewTimesheetStatus::Approved;
    }

    public function export(User $user, CrewTimesheet $timesheet): bool
    {
        return $user->can('crew.timesheets.export')
            && $this->canAccessStore($user, $timesheet->store_id)
            && in_array($timesheet->status, [CrewTimesheetStatus::Approved, CrewTimesheetStatus::Locked], true);
    }

    protected function isOwnRecord(User $user, CrewTimesheet $timesheet): bool
    {
        return $timesheet->crewMember?->user_id === $user->id;
    }
}
