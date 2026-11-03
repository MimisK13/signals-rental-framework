<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Data\UserData;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Webhooks\Services\WebhookService;
use Closure;
use Illuminate\Validation\ValidationException;

final class DeleteOwnAccount
{
    public function blockReason(User $user): ?string
    {
        if ($user->isOwner()) {
            return 'You are the account owner. Transfer ownership before deleting your account.';
        }

        if (User::query()->where('is_active', true)->count() <= 1) {
            return 'You are the last user in the system and cannot delete your account.';
        }

        return null;
    }

    /** @param Closure(): mixed $logout */
    public function __invoke(User $user, Closure $logout): void
    {
        if ($reason = $this->blockReason($user)) {
            throw ValidationException::withMessages(['account' => $reason]);
        }

        $user->tokens()->delete();

        event(new AuditableEvent($user, 'user.deleted'));

        app(WebhookService::class)->dispatch('user.deleted', [
            'user' => UserData::fromModel($user)->toArray(),
        ]);

        $logout();
        $user->delete();
    }
}
