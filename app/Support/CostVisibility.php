<?php

namespace App\Support;

use App\Data\Concerns\StripsCostFields;
use App\Models\Traits\HasCostVisibility;
use App\Models\User;

/**
 * Central cost-visibility checks and field stripping for the `costs.view` permission.
 *
 * DTOs use {@see StripsCostFields} at serialisation time; Eloquent
 * models can use {@see HasCostVisibility} for array-shaped payloads.
 */
final class CostVisibility
{
    /**
     * Whether the given user may view cost-related field values.
     *
     * Accepts an explicit user for queue/CLI contexts. Falls back to the authenticated
     * user when none is provided. Fail-closed when no user is available.
     */
    public static function canView(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user?->can('costs.view') ?? false;
    }

    /**
     * Remove cost-sensitive keys from a serialised array when the user lacks cost visibility.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fieldKeys
     * @return array<string, mixed>
     */
    public static function stripFields(array $data, array $fieldKeys, ?User $user = null): array
    {
        if (self::canView($user)) {
            return $data;
        }

        return array_diff_key($data, array_flip($fieldKeys));
    }
}
