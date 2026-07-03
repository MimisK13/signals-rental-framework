<?php

namespace App\Verbs\Events\Invoices;

use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Verbs\Events\Invoices\Concerns\RecordsInvoiceAudit;
use App\Verbs\States\InvoiceState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Voids an invoice. Draft and issued invoices with no payments may be voided.
 */
class InvoiceVoided extends Event
{
    use RecordsInvoiceAudit;

    public function __construct(
        #[StateId(InvoiceState::class)]
        public int $state_id,
        public string $status = 'void',
        public ?string $voided_at = null,
        /** @var list<array{opportunity_item_id: int, quantity: string}> */
        public array $quantity_releases = [],
    ) {}

    public function validate(InvoiceState $state): void
    {
        $this->assert(
            ! $state->is_void,
            'This invoice is already void.',
        );

        $this->assert(
            in_array($state->status, ['draft', 'issued', 'partially_paid', 'overdue'], true),
            'This invoice cannot be voided in its current status.',
        );
    }

    public function apply(InvoiceState $state): void
    {
        $state->status = $this->status;
        $state->is_void = true;
        $state->voided_at = $this->voided_at !== null
            ? CarbonImmutable::parse($this->voided_at)
            : null;
        $state->last_event_at = $state->voided_at ?? $state->last_event_at;
    }

    public function handle(InvoiceState $state): void
    {
        $oldRow = Invoice::query()->whereKey($state->invoice_id)->first();
        $oldValues = $oldRow !== null ? ['status' => $oldRow->status] : null;

        Invoice::query()
            ->whereKey($state->invoice_id)
            ->update([
                'status' => $state->status,
                'updated_at' => $state->voided_at ?? $state->last_event_at,
            ]);

        $invoice = Invoice::query()->whereKey($state->invoice_id)->firstOrFail();

        foreach ($this->quantity_releases as $release) {
            $item = OpportunityItem::query()->find((int) $release['opportunity_item_id']);

            if ($item === null) {
                continue;
            }

            $newQty = bcsub((string) $item->invoiced_quantity, (string) $release['quantity'], 2);

            if (bccomp($newQty, '0', 2) < 0) {
                $newQty = '0.00';
            }

            OpportunityItem::query()
                ->whereKey($item->id)
                ->update(['invoiced_quantity' => $newQty]);
        }

        if ($this->quantity_releases !== [] && $state->opportunity_id !== null) {
            Opportunity::query()
                ->whereKey($state->opportunity_id)
                ->update(['invoiced' => false]);
        }

        $this->recordInvoiceAudit(
            $invoice,
            'invoice.voided',
            newValues: [
                'status' => $state->status,
                'voided_at' => $state->voided_at?->toIso8601String(),
            ],
            oldValues: $oldValues,
        );
    }
}
