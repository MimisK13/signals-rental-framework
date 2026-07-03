<?php

namespace App\Settings;

use App\Enums\OpportunityState;

/**
 * Tenant invoice configuration scaffold (P30).
 *
 * Number formatting is primarily owned by per-store {@see DocumentSequence}
 * rows (one sequence per invoice type). These settings hold cross-cutting
 * invoice defaults and placeholders for later lifecycle items.
 */
class InvoiceSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'invoices';
    }

    public function defaults(): array
    {
        return [
            'source_states' => [
                'standard' => [OpportunityState::Order->value],
                'deposit' => [
                    OpportunityState::Quotation->value,
                    OpportunityState::Order->value,
                ],
                'proforma' => [
                    OpportunityState::Quotation->value,
                    OpportunityState::Order->value,
                ],
            ],
            // Optional override when a store has no document_sequences row yet.
            'fallback_number_prefix' => 'INV-',
        ];
    }

    public function rules(): array
    {
        return [
            'source_states' => ['nullable', 'array'],
            'fallback_number_prefix' => ['required', 'string', 'max:20'],
        ];
    }

    public function types(): array
    {
        return [
            'source_states' => 'json',
            'fallback_number_prefix' => 'string',
        ];
    }
}
