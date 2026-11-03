<?php

declare(strict_types=1);

namespace App\Domain\AuditLog\Policies;

use App\Domain\AuditLog\Models\ActionLog;
use App\Models\User;

class ActionLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('action-log.view');
    }

    public function view(User $user, ActionLog $actionLog): bool
    {
        return $user->can('action-log.view');
    }
}
