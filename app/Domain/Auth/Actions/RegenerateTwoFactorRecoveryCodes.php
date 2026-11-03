<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Administration\Models\User;

final class RegenerateTwoFactorRecoveryCodes
{
    /**
     * Regenerate the two-factor authentication recovery codes for the given user.
     *
     * @return string[] The new recovery codes
     */
    public function __invoke(User $user): array
    {
        $codes = $user->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => json_encode($codes),
        ])->save();

        return $codes;
    }
}
