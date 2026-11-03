<?php

namespace App\Actions\Admin;

use App\Data\Api\UserData;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Webhooks\Services\WebhookService;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    public function __invoke(User $user): void
    {
        Gate::authorize('users.delete');

        if ($user->isOwner()) {
            throw ValidationException::withMessages([
                'user' => 'The account owner cannot be deleted.',
            ]);
        }

        // Revoke all API tokens
        $user->tokens()->delete();

        event(new AuditableEvent($user, 'user.deleted'));

        app(WebhookService::class)->dispatch('user.deleted', [
            'user' => UserData::fromModel($user)->toArray(),
        ]);

        $user->delete();
    }
}
