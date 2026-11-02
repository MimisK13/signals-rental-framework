<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Verbs\Events;

use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Enums\QuotationVersionType;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Verbs\States\QuotationState;
use App\Domain\Quotations\Verbs\States\QuotationVersionState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

final class QuotationCreated extends Event
{
    public function __construct(
        public int $quotation_id,
        public int $version_id,
        #[StateId(QuotationState::class)]
        public ?int $quotation_state_id = null,
        #[StateId(QuotationVersionState::class)]
        public ?int $version_state_id = null,
        public ?int $project_id = null,
        public int $member_id = 0,
        public ?int $venue_id = null,
        public int $store_id = 0,
        public ?int $owned_by = null,
        public string $number = '',
        public string $subject = '',
        public ?string $reference = null,
        public ?string $description = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public ?string $valid_until = null,
        public string $currency_code = '',
        public string $exchange_rate = '1',
        public bool $prices_include_tax = false,
        public ?string $version_label = null,
        public ?string $version_notes = null,
        public ?int $created_by = null,
    ) {}

    public function applyToQuotation(QuotationState $state): void
    {
        $state->quotation_id = $this->quotation_id;
        $state->project_id = $this->project_id;
        $state->member_id = $this->member_id;
        $state->venue_id = $this->venue_id;
        $state->store_id = $this->store_id;
        $state->owned_by = $this->owned_by;
        $state->number = $this->number;
        $state->subject = $this->subject;
        $state->reference = $this->reference;
        $state->description = $this->description;
        $state->status = QuotationStatus::Draft->value;
        $state->starts_at = $this->parseDate($this->starts_at);
        $state->ends_at = $this->parseDate($this->ends_at);
        $state->valid_until = $this->parseDate($this->valid_until);
        $state->currency_code = $this->currency_code;
        $state->exchange_rate = $this->exchange_rate;
        $state->prices_include_tax = $this->prices_include_tax;
        $state->active_version_id = $this->version_id;
        $state->accepted_version_id = null;
        $state->version_count = 1;
        $state->editor_revision = 1;
        $state->created_by = $this->created_by;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function applyToVersion(QuotationVersionState $state): void
    {
        $state->version_id = $this->version_id;
        $state->quotation_id = $this->quotation_id;
        $state->version_number = 1;
        $state->version_type = QuotationVersionType::Original->value;
        $state->label = $this->version_label ?? 'Version 1';
        $state->is_active = true;
        $state->status = QuotationVersionStatus::Draft->value;
        $state->currency_code = $this->currency_code;
        $state->exchange_rate = $this->exchange_rate;
        $state->prices_include_tax = $this->prices_include_tax;
        $state->notes = $this->version_notes;
        $state->created_by = $this->created_by;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(QuotationState $quotationState, QuotationVersionState $versionState): void
    {
        Quotation::query()->updateOrCreate(
            ['id' => $quotationState->quotation_id],
            [
                'state_id' => $quotationState->id,
                'project_id' => $quotationState->project_id,
                'member_id' => $quotationState->member_id,
                'venue_id' => $quotationState->venue_id,
                'store_id' => $quotationState->store_id,
                'owned_by' => $quotationState->owned_by,
                'number' => $quotationState->number,
                'subject' => $quotationState->subject,
                'reference' => $quotationState->reference,
                'description' => $quotationState->description,
                'status' => $quotationState->status,
                'starts_at' => $quotationState->starts_at,
                'ends_at' => $quotationState->ends_at,
                'valid_until' => $quotationState->valid_until,
                'currency_code' => $quotationState->currency_code,
                'exchange_rate' => $quotationState->exchange_rate,
                'prices_include_tax' => $quotationState->prices_include_tax,
                'active_version_id' => null,
                'accepted_version_id' => null,
                'version_count' => $quotationState->version_count,
                'charge_excluding_tax_total' => $quotationState->charge_excluding_tax_total,
                'tax_total' => $quotationState->tax_total,
                'charge_including_tax_total' => $quotationState->charge_including_tax_total,
                'charge_total' => $quotationState->charge_total,
                'editor_revision' => $quotationState->editor_revision,
                'created_by' => $quotationState->created_by,
                'deleted_at' => null,
            ],
        );

        QuotationVersion::query()->updateOrCreate(
            ['id' => $versionState->version_id],
            [
                'state_id' => $versionState->id,
                'quotation_id' => $versionState->quotation_id,
                'version_number' => $versionState->version_number,
                'parent_version_id' => null,
                'superseded_by_version_id' => null,
                'version_type' => $versionState->version_type,
                'label' => $versionState->label,
                'is_active' => $versionState->is_active,
                'status' => $versionState->status,
                'charge_excluding_tax_total' => $versionState->charge_excluding_tax_total,
                'tax_total' => $versionState->tax_total,
                'charge_including_tax_total' => $versionState->charge_including_tax_total,
                'charge_total' => $versionState->charge_total,
                'currency_code' => $versionState->currency_code,
                'exchange_rate' => $versionState->exchange_rate,
                'prices_include_tax' => $versionState->prices_include_tax,
                'notes' => $versionState->notes,
                'created_by' => $versionState->created_by,
            ],
        );

        Quotation::query()->whereKey($quotationState->quotation_id)->update([
            'active_version_id' => $versionState->version_id,
        ]);
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        return $value !== null ? CarbonImmutable::parse($value) : null;
    }
}
