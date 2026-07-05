<?php

namespace App\Settings;

use Illuminate\Validation\Rule;

/**
 * Tenant pricing configuration for the discount / tax / surcharge pipeline.
 *
 * {@see pricing.rounding_direction} is read at calculation time (fire-time), not
 * cached inside calculators, so a settings change affects the next pricing run
 * without redeploying code.
 */
class PricingSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'pricing';
    }

    public function defaults(): array
    {
        return [
            'rounding_direction' => 'half_up',
        ];
    }

    public function rules(): array
    {
        return [
            'rounding_direction' => [
                'required',
                'string',
                Rule::in(['half_up', 'bankers']),
            ],
        ];
    }

    public function types(): array
    {
        return [
            'rounding_direction' => 'string',
        ];
    }
}
