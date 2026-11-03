<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services\RecipientResolvers;

use App\Domain\Administration\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Contracts\RecipientResolver;
use App\Domain\Notifications\Data\NotificationContextData;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Resolves all user-type members assigned to a named role.
 */
class RoleRecipientResolver implements RecipientResolver
{
    public function type(): string
    {
        return 'role';
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return Collection<int, Member>
     */
    public function resolve(array $rule, NotificationContextData $context): Collection
    {
        $roleName = $rule['role'] ?? $rule['name'] ?? null;

        if (! is_string($roleName) || $roleName === '') {
            return collect();
        }

        $role = Role::query()->where('name', $roleName)->first();

        if ($role === null) {
            return collect();
        }

        $memberIds = User::query()
            ->role($role)
            ->whereNotNull('member_id')
            ->pluck('member_id')
            ->unique()
            ->values();

        if ($memberIds->isEmpty()) {
            return collect();
        }

        return Member::query()
            ->whereIn('id', $memberIds)
            ->get();
    }
}
