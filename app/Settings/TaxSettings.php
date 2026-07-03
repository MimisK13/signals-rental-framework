<?php

namespace App\Settings;

use Illuminate\Validation\Rule;

/**
 * Tenant tax configuration for zone resolution fallbacks.
 */
class TaxSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'tax';
    }

    public function defaults(): array
    {
        return [
            'unresolved_zone_fallback' => 'seller_domestic',
        ];
    }

    public function rules(): array
    {
        return [
            'unresolved_zone_fallback' => [
                'required',
                'string',
                Rule::in(['seller_domestic']),
            ],
        ];
    }

    public function types(): array
    {
        return [
            'unresolved_zone_fallback' => 'string',
        ];
    }
}
