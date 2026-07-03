<?php

namespace App\Actions\Invoices;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Invoices\InvoiceData;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Services\Invoices\InvoiceStatusRegistry;
use App\Verbs\Events\Invoices\InvoiceIssued;
use App\Verbs\Events\Opportunities\OpportunityLocksApplied;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Issues a draft invoice via the InvoiceIssued event.
 */
class IssueInvoice
{
    use CommitsVerbsEvents;

    public function __invoke(Invoice $invoice): InvoiceData
    {
        Gate::authorize('issue', $invoice);

        $issuedAt = CarbonImmutable::now('UTC');
        $dueDays = (int) settings('invoices.due_days', 30);
        $dueAt = $issuedAt->addDays(max(0, $dueDays));
        $shouldLockOpportunity = (bool) settings('invoices.lock_opportunity_on_issue', true);
        $status = 'issued';

        app(InvoiceStatusRegistry::class)->assertKnown($status);
        app(InvoiceStatusRegistry::class)->assertCanTransitionTo($invoice->status, $status);

        $this->commitVerbs(function () use ($invoice, $issuedAt, $dueAt, $shouldLockOpportunity, $status): void {
            InvoiceIssued::fire(
                state_id: (int) $invoice->state_id,
                status: $status,
                issued_at: $issuedAt->toIso8601String(),
                due_at: $dueAt->toIso8601String(),
                is_locked: true,
                lock_opportunity: $shouldLockOpportunity,
            );

            if (! $shouldLockOpportunity || $invoice->opportunity_id === null) {
                return;
            }

            $opportunity = Opportunity::query()->find($invoice->opportunity_id);

            if ($opportunity === null) {
                return;
            }

            if ($opportunity->exchange_rate_locked || $opportunity->tax_locked) {
                return;
            }

            OpportunityLocksApplied::fire(
                opportunity_id: (int) $opportunity->state_id,
                reason: 'Invoice issued',
            );
        });

        return InvoiceData::fromModel($invoice->refresh());
    }
}
