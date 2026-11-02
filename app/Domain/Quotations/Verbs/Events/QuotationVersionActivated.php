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

final class QuotationVersionActivated extends Event
{
    public function __construct(
        public int $version_pk,
        #[StateId(QuotationVersionState::class)]
        public int $version_id,
        #[StateId(QuotationState::class)]
        public int $quotation_id,
        public string $quotation_status,
        public int $charge_excluding_tax_total,
        public int $tax_total,
        public int $charge_including_tax_total,
        public int $charge_total,
    ) {}

    public function validate(QuotationVersionState $versionState, QuotationState $quotationState): void
    {
        $this->assert(
            in_array($versionState->status, [QuotationVersionStatus::Draft->value, QuotationVersionStatus::Sent->value], true),
            'Only a draft or sent version can be activated.',
        );
        $this->assert(
            ! QuotationStatus::from($quotationState->status)->isTerminal(),
            'Versions cannot be activated after a quotation reaches a terminal status.',
        );
    }

    public function applyToVersion(QuotationVersionState $state): void
    {
        $state->is_active = true;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function applyToQuotation(QuotationState $state): void
    {
        $state->active_version_id = $this->version_pk;
        $state->status = $this->quotation_status;
        $state->charge_excluding_tax_total = $this->charge_excluding_tax_total;
        $state->tax_total = $this->tax_total;
        $state->charge_including_tax_total = $this->charge_including_tax_total;
        $state->charge_total = $this->charge_total;
        $state->editor_revision++;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(QuotationVersionState $versionState, QuotationState $quotationState): void
    {
        $version = QuotationVersion::query()->where('state_id', $versionState->id)->first();

        if ($version === null) {
            return;
        }

        QuotationVersion::query()
            ->where('quotation_id', $version->quotation_id)
            ->where('id', '!=', $version->id)
            ->update(['is_active' => false]);

        $version->update(['is_active' => true]);

        Quotation::query()->where('state_id', $quotationState->id)->update([
            'active_version_id' => $version->id,
            'status' => $quotationState->status,
            'charge_excluding_tax_total' => $quotationState->charge_excluding_tax_total,
            'tax_total' => $quotationState->tax_total,
            'charge_including_tax_total' => $quotationState->charge_including_tax_total,
            'charge_total' => $quotationState->charge_total,
            'editor_revision' => $quotationState->editor_revision,
        ]);
    }
}
