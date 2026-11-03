<?php

declare(strict_types=1);

namespace App\Domain\CustomViews\Policies;

use App\Domain\Administration\Models\User;
use App\Domain\CustomViews\Models\CustomView;

final class CustomViewPolicy
{
    /**
     * Anyone can view custom views (visibility filtering happens in scopes).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CustomView $view): bool
    {
        if ($view->visibility === 'system') {
            return true;
        }

        if ($view->visibility === 'personal') {
            return $view->user_id === $user->id;
        }

        return $view->roles()
            ->whereIn('roles.id', $user->roles->pluck('id'))
            ->exists();
    }

    /**
     * Any authenticated user can create views.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Personal views: only the owner can update.
     * Shared/system views: requires settings.manage permission.
     */
    public function update(User $user, CustomView $view): bool
    {
        if ($view->visibility === 'personal') {
            return $view->user_id === $user->id;
        }

        return $user->can('settings.manage');
    }

    /**
     * Personal views: only the owner can delete.
     * System views: cannot be deleted.
     * Shared views: requires settings.manage permission.
     */
    public function delete(User $user, CustomView $view): bool
    {
        if ($view->visibility === 'system') {
            return false;
        }

        if ($view->visibility === 'personal') {
            return $view->user_id === $user->id;
        }

        return $user->can('settings.manage');
    }
}
