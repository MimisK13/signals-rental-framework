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
            'due_days' => 30,
            'lock_opportunity_on_issue' => true,
            'auto_email_on_issue' => true,
            'auto_email_on_credit_note' => true,
            'auto_email_on_payment' => true,
        ];
    }

    public function rules(): array
    {
        return [
            'source_states' => ['nullable', 'array'],
            'fallback_number_prefix' => ['required', 'string', 'max:20'],
            'due_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'lock_opportunity_on_issue' => ['required', 'boolean'],
            'auto_email_on_issue' => ['required', 'boolean'],
            'auto_email_on_credit_note' => ['required', 'boolean'],
            'auto_email_on_payment' => ['required', 'boolean'],
        ];
    }

    public function types(): array
    {
        return [
            'source_states' => 'json',
            'fallback_number_prefix' => 'string',
            'due_days' => 'integer',
            'lock_opportunity_on_issue' => 'boolean',
            'auto_email_on_issue' => 'boolean',
            'auto_email_on_credit_note' => 'boolean',
            'auto_email_on_payment' => 'boolean',
        ];
    }
}
