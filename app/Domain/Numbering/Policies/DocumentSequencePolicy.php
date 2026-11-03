<?php

declare(strict_types=1);

namespace App\Domain\Numbering\Policies;

use App\Domain\Administration\Models\User;
use App\Domain\Numbering\Models\DocumentSequence;
use App\Domain\Stores\Policies\Concerns\ChecksStoreAccess;
use App\Policies\Traits\AuthorizesByPermission;
use Illuminate\Database\Eloquent\Model;

final class DocumentSequencePolicy
{
    use AuthorizesByPermission, ChecksStoreAccess;

    protected function viewPermission(): string
    {
        return 'numbering.view';
    }

    protected function managePermission(): string
    {
        return 'numbering.edit';
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
