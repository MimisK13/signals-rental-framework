<?php

namespace App\Policies;

use App\Models\Container;
use App\Models\User;
use App\Policies\Traits\ChecksStoreAccess;

class ContainerPolicy
{
    use ChecksStoreAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('containers.view');
    }

    public function view(User $user, Container $container): bool
    {
        return $user->can('containers.view') && $this->canAccessContainerStore($user, $container);
    }

    public function create(User $user): bool
    {
        return $user->can('containers.create');
    }

    public function createTemporary(User $user): bool
    {
        return $user->can('containers.create_temporary');
    }

    public function update(User $user, Container $container): bool
    {
        return $user->can('containers.create') && $this->canAccessContainerStore($user, $container);
    }

    public function pack(User $user, Container $container): bool
    {
        return $user->can('containers.pack') && $this->canAccessContainerStore($user, $container);
    }

    public function packOverride(User $user, Container $container): bool
    {
        return $user->can('containers.pack_override') && $this->canAccessContainerStore($user, $container);
    }

    public function transfer(User $user, Container $container): bool
    {
        return $user->can('containers.transfer') && $this->canAccessContainerStore($user, $container);
    }

    public function nest(User $user, Container $container): bool
    {
        return $user->can('containers.nest') && $this->canAccessContainerStore($user, $container);
    }

    public function seal(User $user, Container $container): bool
    {
        return $user->can('containers.seal') && $this->canAccessContainerStore($user, $container);
    }

    public function unseal(User $user, Container $container): bool
    {
        return $user->can('containers.seal') && $this->canAccessContainerStore($user, $container);
    }

    public function dissolve(User $user, Container $container): bool
    {
        return $user->can('containers.dissolve') && $this->canAccessContainerStore($user, $container);
    }

    /**
     * Transport-mode dispatch against an opportunity (serialised-containers.md §Permissions).
     * Kit/hybrid persistent dispatch uses the same gate.
     *
     * Requires opportunity dispatch permission ({@see opportunities.edit}) plus
     * {@see containers.view}. Does not require {@see containers.dissolve}.
     */
    public function dispatch(User $user, Container $container): bool
    {
        return $user->can('containers.view')
            && $user->can('opportunities.edit')
            && $this->canAccessContainerStore($user, $container);
    }

    /**
     * Kit/hybrid return — mirrors {@see ReturnAsset} ({@see opportunities.edit}).
     */
    public function return(User $user, Container $container): bool
    {
        return $user->can('containers.view')
            && $user->can('opportunities.edit')
            && $this->canAccessContainerStore($user, $container);
    }

    /**
     * Clear a parent-then-verify check-in flag after condition assessment.
     */
    public function verifyCheckin(User $user, Container $container): bool
    {
        return $this->return($user, $container);
    }

    public function manageTemplates(User $user): bool
    {
        return $user->can('containers.manage_templates');
    }

    /**
     * Resolve store access for a container. Product-backed containers may omit
     * `store_id` on the row and inherit it from the housing serialised asset.
     */
    protected function canAccessContainerStore(User $user, Container $container): bool
    {
        $storeIds = $user->accessibleStoreIds();

        if ($storeIds === null) {
            return true;
        }

        $storeId = $container->store_id ?? $container->serialisedItem?->store_id;

        if ($storeId === null) {
            return true;
        }

        return in_array($storeId, $storeIds, true);
    }
}
