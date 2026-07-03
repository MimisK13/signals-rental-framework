<?php

namespace App\Settings;

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
            // Placeholder for P31 generate-from-opportunity source-state rules.
            'source_states' => [],
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
