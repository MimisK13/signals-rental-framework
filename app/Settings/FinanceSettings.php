<?php

namespace App\Settings;

use Illuminate\Validation\Rule;

/**
 * Tenant finance configuration for tax timing and pricing display defaults.
 *
 * Settings are read at fire-time (not cached inside calculators) so changes
 * apply to the next tax or opportunity operation without redeploying code.
 */
class FinanceSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'finance';
    }

    public function defaults(): array
    {
        return [
            'tax_point_policy' => 'supply_date',
            'prices_include_tax_default' => false,
            'show_tax_on_documents' => true,
        ];
    }

    public function rules(): array
    {
        return [
            'tax_point_policy' => [
                'required',
                'string',
                Rule::in(['invoice_date', 'supply_date']),
            ],
            'prices_include_tax_default' => ['required', 'boolean'],
            'show_tax_on_documents' => ['required', 'boolean'],
        ];
    }

    public function types(): array
    {
        return [
            'tax_point_policy' => 'string',
            'prices_include_tax_default' => 'boolean',
            'show_tax_on_documents' => 'boolean',
        ];
    }
}
