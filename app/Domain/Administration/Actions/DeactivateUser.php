<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Data\UserData;
use App\Domain\Administration\Models\User;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeactivateUser
{
    public function __invoke(User $user): User
    {
        Gate::authorize('users.deactivate');

        if ($user->isOwner()) {
            throw ValidationException::withMessages([
                'user' => 'The account owner cannot be deactivated.',
            ]);
        }

        $user->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        // Revoke all API tokens
        $user->tokens()->delete();

        /** @var User $user */
        $user = $user->fresh();

        app(WebhookService::class)->dispatch('user.deactivated', [
            'user' => UserData::fromModel($user)->toArray(),
        ]);

        return $user;
    }
}
