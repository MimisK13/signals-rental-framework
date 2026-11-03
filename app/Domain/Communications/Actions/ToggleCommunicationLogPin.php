<?php

declare(strict_types=1);

namespace App\Domain\Communications\Actions;

use App\Domain\Administration\Models\User;
use App\Domain\Communications\Models\CommunicationLog;
use Illuminate\Support\Facades\Gate;

final class ToggleCommunicationLogPin
{
    public function __invoke(User $user, CommunicationLog $log): CommunicationLog
    {
        Gate::forUser($user)->authorize('update', $log);

        $log->update(['is_pinned' => ! $log->is_pinned]);

        return $log->fresh();
    }
}
