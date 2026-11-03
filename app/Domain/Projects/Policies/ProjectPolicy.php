<?php

declare(strict_types=1);

namespace App\Domain\Projects\Policies;

use App\Domain\Administration\Models\User;
use App\Domain\Projects\Models\Project;

final class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can('projects.view');
    }

    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('projects.edit');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('projects.delete');
    }
}
