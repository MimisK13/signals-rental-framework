<?php

namespace App\Services\Tax;

use App\Enums\TaxPointPolicy;
use App\Models\Opportunity;
use DateTimeInterface;

/**
 * Resolves the tax-point date used for rate and zone rule effective dating.
 *
 * Reads {@see finance.tax_point_policy} at fire-time. Supply date prefers the
 * delivery window; invoice date prefers an explicit invoice timestamp when
 * supplied (P31) and otherwise falls back to the opportunity order date.
 */
class TaxPointDateResolver
{
    public function resolveForOpportunity(Opportunity $opportunity, ?DateTimeInterface $invoiceDate = null): ?DateTimeInterface
    {
        $policy = TaxPointPolicy::tryFrom(
            (string) settings('finance.tax_point_policy', TaxPointPolicy::SupplyDate->value),
        ) ?? TaxPointPolicy::SupplyDate;

        return match ($policy) {
            TaxPointPolicy::InvoiceDate => $invoiceDate
                ?? $opportunity->starts_at
                ?? $opportunity->created_at,
            TaxPointPolicy::SupplyDate => $opportunity->deliver_starts_at
                ?? $opportunity->charge_starts_at
                ?? $opportunity->starts_at
                ?? $opportunity->created_at,
        };
    }
}
