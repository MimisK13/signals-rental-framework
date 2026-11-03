<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Models\User;
use Illuminate\Support\Facades\Gate;

final class ReactivateUser
{
    public function __invoke(User $user): User
    {
        Gate::authorize('users.activate');

        $user->update([
            'is_active' => true,
            'deactivated_at' => null,
        ]);

        return $user->fresh();
    }
}
