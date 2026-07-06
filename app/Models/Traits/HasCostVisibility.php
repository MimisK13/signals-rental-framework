<?php

namespace App\Models\Traits;

use App\Models\User;
use App\Support\CostVisibility;

trait HasCostVisibility
{
    /**
     * Check if a user can view cost-related fields.
     *
     * Accepts an explicit user for queue/CLI contexts. Falls back to the
     * authenticated user when none is provided.
     */
    public function canViewCosts(?User $user = null): bool
    {
        return CostVisibility::canView($user);
    }

    /**
     * Get the list of cost-related column names for this model.
     *
     * Models using this trait must declare a `protected array $costColumns` property
     * listing their cost-sensitive column names.
     *
     * @return list<string>
     */
    public function costColumns(): array
    {
        return $this->costColumns;
    }

    /**
     * Strip cost fields from a data array if the user cannot view costs.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function withoutCostColumns(array $data, ?User $user = null): array
    {
        return CostVisibility::stripFields($data, $this->costColumns(), $user);
    }
}
