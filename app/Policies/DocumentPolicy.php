<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use App\Policies\Traits\AuthorizesByPermission;
use App\Policies\Traits\ChecksStoreAccess;
use Illuminate\Database\Eloquent\Model;

class DocumentPolicy
{
    use AuthorizesByPermission, ChecksStoreAccess;

    protected function viewPermission(): string
    {
        return 'documents.view';
    }

    protected function managePermission(): string
    {
        return 'documents.create';
    }

    protected function createPermission(): string
    {
        return 'documents.create';
    }

    protected function deletePermission(): string
    {
        return 'documents.delete';
    }

    public function view(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->viewPermission())) {
            return false;
        }

        if ($model instanceof Document) {
            return $this->canAccessStore($user, $model);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can($this->createPermission());
    }

    public function finalise(User $user, Document $document): bool
    {
        if (! $user->can('documents.finalise')) {
            return false;
        }

        if (! $this->canAccessStore($user, $document)) {
            return false;
        }

        return $document->status === DocumentStatus::Draft;
    }

    public function void(User $user, Document $document): bool
    {
        if (! $user->can($this->deletePermission())) {
            return false;
        }

        if (! $this->canAccessStore($user, $document)) {
            return false;
        }

        return $document->status === DocumentStatus::Final;
    }

    public function share(User $user, Document $document): bool
    {
        if (! $user->can('documents.share')) {
            return false;
        }

        if (! $this->canAccessStore($user, $document)) {
            return false;
        }

        return $document->status !== DocumentStatus::Void;
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->deletePermission())) {
            return false;
        }

        if ($model instanceof Document) {
            return $this->canAccessStore($user, $model);
        }

        return true;
    }
}
