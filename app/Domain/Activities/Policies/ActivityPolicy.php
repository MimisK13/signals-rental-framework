<?php

declare(strict_types=1);

namespace App\Domain\Activities\Policies;

use App\Policies\Traits\AuthorizesByPermission;

final class ActivityPolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'activities.view';
    }

    protected function managePermission(): string
    {
        return 'activities.create';
    }

    protected function createPermission(): string
    {
        return 'activities.create';
    }

    protected function editPermission(): string
    {
        return 'activities.edit';
    }

    protected function deletePermission(): string
    {
        return 'activities.delete';
    }
}
