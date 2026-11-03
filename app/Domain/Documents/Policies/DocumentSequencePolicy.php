<?php

declare(strict_types=1);

namespace App\Domain\Documents\Policies;

use App\Domain\Documents\Models\DocumentSequence;
use App\Domain\Stores\Policies\Concerns\ChecksStoreAccess;
use App\Models\User;
use App\Policies\Traits\AuthorizesByPermission;
use Illuminate\Database\Eloquent\Model;

class DocumentSequencePolicy
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

    public function view(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->viewPermission())) {
            return false;
        }

        if ($model instanceof DocumentSequence) {
            return $this->canAccessStore($user, $model);
        }

        return true;
    }

    public function update(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->editPermission())) {
            return false;
        }

        if ($model instanceof DocumentSequence) {
            return $this->canAccessStore($user, $model);
        }

        return true;
    }
}
