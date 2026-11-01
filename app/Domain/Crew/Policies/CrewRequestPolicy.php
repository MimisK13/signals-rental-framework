<?php

declare(strict_types=1);

namespace App\Domain\Crew\Policies;

use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Policies\Concerns\ChecksStoreAccess;
use App\Models\User;

class CrewRequestPolicy
{
    use ChecksStoreAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('crew.requests.view');
    }

    public function view(User $user, CrewRequest $request): bool
    {
        return $user->can('crew.requests.view')
            && $this->canAccessStore($user, $request->store_id);
    }

    public function create(User $user): bool
    {
        return $user->can('crew.requests.create');
    }

    public function update(User $user, CrewRequest $request): bool
    {
        return $user->can('crew.requests.edit')
            && $this->canAccessStore($user, $request->store_id)
            && ! in_array($request->status, [CrewRequestStatus::Cancelled], true);
    }

    public function publish(User $user, CrewRequest $request): bool
    {
        return $user->can('crew.requests.publish')
            && $this->canAccessStore($user, $request->store_id)
            && in_array($request->status, [CrewRequestStatus::Draft, CrewRequestStatus::Open], true);
    }

    public function cancel(User $user, CrewRequest $request): bool
    {
        return $user->can('crew.requests.cancel')
            && $this->canAccessStore($user, $request->store_id)
            && $request->status !== CrewRequestStatus::Cancelled;
    }

    public function delete(User $user, CrewRequest $request): bool
    {
        return $user->can('crew.requests.edit')
            && $this->canAccessStore($user, $request->store_id)
            && $request->status === CrewRequestStatus::Draft;
    }
}
