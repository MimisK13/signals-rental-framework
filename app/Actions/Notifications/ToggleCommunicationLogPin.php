<?php

namespace App\Actions\Notifications;

use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ToggleCommunicationLogPin
{
    public function __invoke(User $user, CommunicationLog $log): CommunicationLog
    {
        Gate::forUser($user)->authorize('update', $log);

        $log->update(['is_pinned' => ! $log->is_pinned]);

        return $log->fresh();
    }
}
