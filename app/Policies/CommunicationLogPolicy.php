<?php

namespace App\Policies;

use App\Models\CommunicationLog;
use App\Models\Opportunity;
use App\Models\User;
use App\Policies\Traits\AuthorizesByPermission;
use Illuminate\Database\Eloquent\Model;

class CommunicationLogPolicy
{
    use AuthorizesByPermission;

    protected function managePermission(): string
    {
        return 'notifications.manage';
    }

    public function view(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->viewPermission())) {
            return false;
        }

        if (! $model instanceof CommunicationLog) {
            return true;
        }

        return $this->canAccessSubjectStore($user, $model);
    }

    public function update(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->editPermission())) {
            return false;
        }

        if (! $model instanceof CommunicationLog) {
            return true;
        }

        return $this->canAccessSubjectStore($user, $model);
    }

    private function canAccessSubjectStore(User $user, CommunicationLog $log): bool
    {
        $storeIds = $user->accessibleStoreIds();

        if ($storeIds === null) {
            return true;
        }

        if ($log->subject_type !== Opportunity::class) {
            return true;
        }

        $log->loadMissing('subject');

        if (! $log->subject instanceof Opportunity) {
            return true;
        }

        return in_array((int) $log->subject->store_id, $storeIds, true);
    }
}
