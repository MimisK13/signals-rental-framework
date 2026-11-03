<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Data\UserData;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeleteUser
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
