<?php

declare(strict_types=1);

namespace App\Domain\Crew\Policies;

use App\Domain\Administration\Models\User;
use App\Domain\Crew\Enums\CrewAvailabilityStatus;
use App\Domain\Crew\Models\CrewAvailability;

class CrewAvailabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crew.availability.view');
    }

    /**
     * Crew members may always see their own availability and leave requests.
     */
    public function view(User $user, CrewAvailability $availability): bool
    {
        return $user->can('crew.availability.view') || $this->isOwnRecord($user, $availability);
    }

    public function create(User $user): bool
    {
        return $user->can('crew.availability.request');
    }

    public function update(User $user, CrewAvailability $availability): bool
    {
        return $user->can('crew.availability.request')
            && $availability->status === CrewAvailabilityStatus::Requested;
    }

    public function approve(User $user, CrewAvailability $availability): bool
    {
        return $user->can('crew.availability.approve')
            && $availability->status === CrewAvailabilityStatus::Requested;
    }

    public function reject(User $user, CrewAvailability $availability): bool
    {
        return $user->can('crew.availability.approve')
            && $availability->status === CrewAvailabilityStatus::Requested;
    }

    public function cancel(User $user, CrewAvailability $availability): bool
    {
        return ($user->can('crew.availability.request') && $availability->status === CrewAvailabilityStatus::Requested)
            || ($user->can('crew.availability.approve') && in_array($availability->status, [CrewAvailabilityStatus::Requested, CrewAvailabilityStatus::Approved], true));
    }

    protected function isOwnRecord(User $user, CrewAvailability $availability): bool
    {
        return $availability->crewMember?->user_id === $user->id;
    }
}
