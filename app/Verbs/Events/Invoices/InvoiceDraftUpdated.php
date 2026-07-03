<?php

namespace App\Verbs\Events\Invoices;

use App\Models\Invoice;
use App\Verbs\States\InvoiceState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Updates draft-only header fields (notes, reference).
 */
class InvoiceDraftUpdated extends Event
{
    public function __construct(
        #[StateId(InvoiceState::class)]
        public int $state_id,
        public ?string $notes = null,
        public ?string $reference = null,
        public ?string $updated_at = null,
        public bool $notes_provided = false,
        public bool $reference_provided = false,
    ) {}

    public function validate(InvoiceState $state): void
    {
        $this->assert(
            $state->status === 'draft' && ! $state->is_locked,
            'Only draft invoices can be edited.',
        );
    }

    public function apply(InvoiceState $state): void
    {
        if ($this->notes_provided) {
            $state->notes = $this->notes;
        }

        if ($this->reference_provided) {
            $state->reference = $this->reference;
        }

        $state->last_event_at = $this->updated_at !== null
            ? CarbonImmutable::parse($this->updated_at)
            : $state->last_event_at;
    }

    public function handle(InvoiceState $state): void
    {
        $payload = [
            'updated_at' => $state->last_event_at,
        ];

        if ($this->notes_provided) {
            $payload['notes'] = $state->notes;
        }

        if ($this->reference_provided) {
            $payload['reference'] = $state->reference;
        }

        Invoice::query()
            ->whereKey($state->invoice_id)
            ->update($payload);
    }
}
