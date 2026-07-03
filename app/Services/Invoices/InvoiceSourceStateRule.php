<?php

namespace App\Services\Invoices;

use App\Enums\InvoiceType;
use App\Enums\OpportunityState;
use App\Models\Opportunity;
use Illuminate\Validation\ValidationException;

/**
 * Config-driven source-state guard for generate-from-opportunity checkout.
 *
 * Reads {@see settings('invoices.source_states')} at fire-time. When the setting
 * is empty, {@see InvoiceSettings::defaults()} supplies the seeded rules.
 */
class InvoiceSourceStateRule
{
    /**
     * @return list<int>
     */
    public function allowedStatesFor(InvoiceType $type): array
    {
        $defaults = $this->defaultRules();

        /** @var array<string, list<int>> $configured */
        $configured = settings('invoices.source_states', $defaults);

        if ($configured === []) {
            $configured = $defaults;
        }

        $states = $configured[$type->value] ?? $defaults[$type->value] ?? [];

        return array_map('intval', $states);
    }

    public function assertAllowed(Opportunity $opportunity, InvoiceType $type): void
    {
        $allowed = $this->allowedStatesFor($type);

        if ($allowed === []) {
            throw ValidationException::withMessages([
                'type' => ["No source states are configured for {$type->label()} invoices."],
            ]);
        }

        if (! in_array($opportunity->state->value, $allowed, true)) {
            $labels = array_map(
                fn (int $state): string => OpportunityState::from($state)->label(),
                $allowed,
            );

            throw ValidationException::withMessages([
                'opportunity_id' => [
                    sprintf(
                        '%s invoices require the opportunity to be in one of: %s. Current state: %s.',
                        $type->label(),
                        implode(', ', $labels),
                        $opportunity->state->label(),
                    ),
                ],
            ]);
        }
    }

    /**
     * @return array<string, list<int>>
     */
    public function defaultRules(): array
    {
        return [
            InvoiceType::Standard->value => [OpportunityState::Order->value],
            InvoiceType::Deposit->value => [
                OpportunityState::Quotation->value,
                OpportunityState::Order->value,
            ],
            InvoiceType::Proforma->value => [
                OpportunityState::Quotation->value,
                OpportunityState::Order->value,
            ],
        ];
    }
}
