<?php

declare(strict_types=1);

namespace App\Domain\Crew\Policies\Concerns;

use App\Models\User;

/**
 * Store visibility guard shared by every Crew policy.
 *
 * `StoreScope` only filters queries when an authenticated user is present, so
 * single-model policy checks must still verify the record's store explicitly.
 */
trait ChecksStoreAccess
{
    protected function canAccessStore(User $user, ?int $storeId): bool
    {
        return $user->canAccessStore($storeId);
    }

    /**
     * Store-optional records (company-wide rate cards) are visible to anyone who
     * may view the resource type at all.
     */
    protected function canAccessOptionalStore(User $user, ?int $storeId): bool
    {
        return $storeId === null || $user->canAccessStore($storeId);
    }
}
