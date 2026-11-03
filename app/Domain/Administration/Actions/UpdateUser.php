<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Data\UserData;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\Gate;

final class UpdateUser
{
    /**
     * @param  array{name?: string, email?: string, roles?: list<string>}  $data
     */
    public function __invoke(User $user, array $data): User
    {
        Gate::authorize('users.edit');

        $oldValues = $user->only(['name', 'email']);
        $oldValues['roles'] = $user->getRoleNames()->toArray();

        $user->update(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
        ], fn ($value) => $value !== null));

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // Keep the linked member record's name in sync with the user's name.
        $user->syncMemberName();

        /** @var User $user */
        $user = $user->fresh();

        $newValues = $user->only(['name', 'email']);
        $newValues['roles'] = $user->getRoleNames()->toArray();

        if ($oldValues !== $newValues) {
            event(new AuditableEvent($user, 'updated', $oldValues, $newValues));
        }

        app(WebhookService::class)->dispatch('user.updated', [
            'user' => UserData::fromModel($user)->toArray(),
        ]);

        return $user;
    }
}
