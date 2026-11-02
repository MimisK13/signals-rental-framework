<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Verbs\Events;

use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Verbs\States\QuotationState;
use App\Domain\Quotations\Verbs\States\QuotationVersionState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

final class QuotationVersionAccepted extends Event
{
    public function __construct(
        public int $version_pk,
        #[StateId(QuotationVersionState::class)]
        public int $version_id,
        #[StateId(QuotationState::class)]
        public int $quotation_id,
        public int $project_id,
        public int $accepted_by,
        public string $accepted_at,
        public int $charge_excluding_tax_total,
        public int $tax_total,
        public int $charge_including_tax_total,
        public int $charge_total,
    ) {}

    public function validate(QuotationVersionState $versionState, QuotationState $quotationState): void
    {
        $this->assert($versionState->is_active, 'Only the active version can be accepted.');
        $this->assert(
            in_array($versionState->status, [QuotationVersionStatus::Draft->value, QuotationVersionStatus::Sent->value], true),
            'Only a draft or sent version can be accepted.',
        );
        $this->assert(
            ! QuotationStatus::from($quotationState->status)->isTerminal(),
            'A terminal quotation cannot be accepted.',
        );
    }

    public function applyToVersion(QuotationVersionState $state): void
    {
        $state->status = QuotationVersionStatus::Accepted->value;
        $state->accepted_by = $this->accepted_by;
        $state->accepted_at = CarbonImmutable::parse($this->accepted_at);
        $state->is_active = true;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function applyToQuotation(QuotationState $state): void
    {
        $state->project_id = $this->project_id;
        $state->status = QuotationStatus::Accepted->value;
        $state->active_version_id = $this->version_pk;
        $state->accepted_version_id = $this->version_pk;
        $state->accepted_by = $this->accepted_by;
        $state->accepted_at = CarbonImmutable::parse($this->accepted_at);
        $state->charge_excluding_tax_total = $this->charge_excluding_tax_total;
        $state->tax_total = $this->tax_total;
        $state->charge_including_tax_total = $this->charge_including_tax_total;
        $state->charge_total = $this->charge_total;
        $state->editor_revision++;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(QuotationVersionState $versionState, QuotationState $quotationState): void
    {
        QuotationVersion::query()->where('state_id', $versionState->id)->update([
            'status' => $versionState->status,
            'accepted_by' => $versionState->accepted_by,
            'accepted_at' => $versionState->accepted_at,
            'is_active' => true,
        ]);
        Quotation::query()->where('state_id', $quotationState->id)->update([
            'project_id' => $quotationState->project_id,
            'status' => $quotationState->status,
            'active_version_id' => $quotationState->active_version_id,
            'accepted_version_id' => $quotationState->accepted_version_id,
            'accepted_by' => $quotationState->accepted_by,
            'accepted_at' => $quotationState->accepted_at,
            'charge_excluding_tax_total' => $quotationState->charge_excluding_tax_total,
            'tax_total' => $quotationState->tax_total,
            'charge_including_tax_total' => $quotationState->charge_including_tax_total,
            'charge_total' => $quotationState->charge_total,
            'editor_revision' => $quotationState->editor_revision,
        ]);
    }
}
