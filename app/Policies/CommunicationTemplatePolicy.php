<?php

namespace App\Policies;

use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Policies\Traits\AuthorizesByPermission;
use App\Policies\Traits\ChecksStoreAccess;
use Illuminate\Database\Eloquent\Model;

class CommunicationTemplatePolicy
{
    use AuthorizesByPermission, ChecksStoreAccess;

    protected function viewPermission(): string
    {
        return 'notifications.manage';
    }

    protected function managePermission(): string
    {
        return 'notifications.manage';
    }

    public function view(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->viewPermission())) {
            return false;
        }

        if ($model instanceof CommunicationTemplate) {
            return $this->canAccessStore($user, $model);
        }

        return true;
    }

    public function update(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->editPermission())) {
            return false;
        }

        if ($model instanceof CommunicationTemplate) {
            return $this->canAccessStore($user, $model);
        }

        return true;
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->deletePermission())) {
            return false;
        }

        if ($model instanceof CommunicationTemplate) {
            if ($model->is_system) {
                return false;
            }

            return $this->canAccessStore($user, $model);
        }

        return true;
    }
}
