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
            'default_product_tax_class_id' => null,
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
            'default_product_tax_class_id' => ['nullable', 'integer', 'exists:product_tax_classes,id'],
        ];
    }

    public function types(): array
    {
        return [
            'unresolved_zone_fallback' => 'string',
            'default_product_tax_class_id' => 'integer',
        ];
    }
}
