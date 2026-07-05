<?php

namespace App\Policies;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Policies\Traits\AuthorizesByPermission;
use App\Policies\Traits\ChecksStoreAccess;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplatePolicy
{
    use AuthorizesByPermission, ChecksStoreAccess;

    protected function viewPermission(): string
    {
        return 'document_templates.view';
    }

    protected function managePermission(): string
    {
        return 'document_templates.edit';
    }

    protected function createPermission(): string
    {
        return 'document_templates.edit';
    }

    protected function editPermission(): string
    {
        return 'document_templates.edit';
    }

    protected function deletePermission(): string
    {
        return 'document_templates.edit';
    }

    public function view(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->viewPermission())) {
            return false;
        }

        if ($model instanceof DocumentTemplate) {
            return $this->canAccessStore($user, $model);
        }

        return true;
    }

    public function update(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->editPermission())) {
            return false;
        }

        if ($model instanceof DocumentTemplate) {
            return $this->canAccessStore($user, $model);
        }

        return true;
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->deletePermission())) {
            return false;
        }

        if ($model instanceof DocumentTemplate) {
            return $this->canAccessStore($user, $model);
        }

        return true;
    }
}
