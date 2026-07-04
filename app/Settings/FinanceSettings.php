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
            'partial_invoice_tax' => 'proportional',
            'vat_validation_validity_days' => 30,
            'reverse_charge_label' => 'Reverse charge',
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
            'partial_invoice_tax' => [
                'required',
                'string',
                Rule::in(['proportional', 'full']),
            ],
            'vat_validation_validity_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'reverse_charge_label' => ['required', 'string', 'max:255'],
        ];
    }

    public function types(): array
    {
        return [
            'tax_point_policy' => 'string',
            'prices_include_tax_default' => 'boolean',
            'show_tax_on_documents' => 'boolean',
            'partial_invoice_tax' => 'string',
            'vat_validation_validity_days' => 'integer',
            'reverse_charge_label' => 'string',
        ];
    }
}
