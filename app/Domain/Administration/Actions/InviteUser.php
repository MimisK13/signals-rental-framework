<?php

declare(strict_types=1);

namespace App\Domain\Administration\Actions;

use App\Domain\Administration\Data\InviteUserData;
use App\Domain\Administration\Data\UserData;
use App\Domain\Administration\Models\User;
use App\Domain\Members\Enums\MembershipType;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Notifications\UserInvitedNotification;
use App\Domain\Webhooks\Services\WebhookService;
use App\Enums\RoleLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class InviteUser
{
    public function __invoke(InviteUserData $data): User
    {
        Gate::authorize('users.invite');

        $this->validateRoleHierarchy($data);

        $user = DB::transaction(function () use ($data): User {
            $member = Member::create([
                'name' => $data->name,
                'membership_type' => MembershipType::User,
                'is_active' => true,
            ]);

            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => null,
                'email_verified_at' => now(),
                'invited_at' => now(),
                'is_active' => true,
                'member_id' => $member->id,
            ]);

            if (! empty($data->roles)) {
                $user->syncRoles($data->roles);
            }

            return $user;
        });

        $user->notify(new UserInvitedNotification);

        app(WebhookService::class)->dispatch('user.created', [
            'user' => UserData::fromModel($user)->toArray(),
        ]);

        return $user;
    }

    /**
     * Validate that the acting user's role level is high enough to assign the target roles.
     *
     * @throws ValidationException
     */
    private function validateRoleHierarchy(InviteUserData $data): void
    {
        if (empty($data->roles)) {
            return;
        }

        /** @var User $actingUser */
        $actingUser = auth()->user();
        $actingLevel = RoleLevel::forUser($actingUser);

        $tooHigh = [];
        foreach ($data->roles as $roleName) {
            if (RoleLevel::levelFor($roleName) >= $actingLevel) {
                $tooHigh[] = $roleName;
            }
        }

        if ($tooHigh !== []) {
            throw ValidationException::withMessages([
                'roles' => 'You cannot assign roles at or above your own level: '.implode(', ', $tooHigh),
            ]);
        }
    }
}
