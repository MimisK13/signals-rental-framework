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

final class QuotationVersionCreated extends Event
{
    public function __construct(
        public int $version_pk,
        public int $quotation_pk,
        public int $version_number,
        #[StateId(QuotationVersionState::class)]
        public ?int $version_id = null,
        #[StateId(QuotationState::class)]
        public int $quotation_id = 0,
        public string $version_type = 'revision',
        public ?int $parent_version_id = null,
        public ?string $label = null,
        public ?string $notes = null,
        public ?int $created_by = null,
        public string $currency_code = '',
        public string $exchange_rate = '1',
        public bool $prices_include_tax = false,
    ) {}

    public function validate(QuotationState $quotationState): void
    {
        $this->assert(
            ! QuotationStatus::from($quotationState->status)->isTerminal(),
            'Versions cannot be created after a quotation reaches a terminal status.',
        );
        $this->assert(
            in_array($this->version_type, [QuotationVersionType::Revision->value, QuotationVersionType::Alternative->value], true),
            'Choose a revision or alternative version type.',
        );
        $this->assert($quotationState->version_count < 20, 'This quotation has reached its version limit.');
    }

    public function applyToVersion(QuotationVersionState $state): void
    {
        $state->version_id = $this->version_pk;
        $state->quotation_id = $this->quotation_pk;
        $state->version_number = $this->version_number;
        $state->parent_version_id = $this->parent_version_id;
        $state->version_type = $this->version_type;
        $state->label = $this->label ?? 'Version '.$this->version_number;
        $state->is_active = true;
        $state->status = QuotationVersionStatus::Draft->value;
        $state->currency_code = $this->currency_code;
        $state->exchange_rate = $this->exchange_rate;
        $state->prices_include_tax = $this->prices_include_tax;
        $state->notes = $this->notes;
        $state->created_by = $this->created_by;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function applyToQuotation(QuotationState $state): void
    {
        $state->active_version_id = $this->version_pk;
        $state->version_count = $this->version_number;
        $state->status = QuotationStatus::Draft->value;
        $state->charge_excluding_tax_total = 0;
        $state->tax_total = 0;
        $state->charge_including_tax_total = 0;
        $state->charge_total = 0;
        $state->deal_total = null;
        $state->deal_price_mode = 'flat_headline';
        $state->deal_price_original_total = null;
        $state->editor_revision++;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(QuotationVersionState $versionState, QuotationState $quotationState): void
    {
        QuotationVersion::query()
            ->where('quotation_id', $this->quotation_pk)
            ->where('id', '!=', $this->version_pk)
            ->update(['is_active' => false]);

        QuotationVersion::query()->updateOrCreate(
            ['id' => $versionState->version_id],
            [
                'state_id' => $versionState->id,
                'quotation_id' => $versionState->quotation_id,
                'version_number' => $versionState->version_number,
                'parent_version_id' => $versionState->parent_version_id,
                'superseded_by_version_id' => null,
                'version_type' => $versionState->version_type,
                'label' => $versionState->label,
                'is_active' => true,
                'status' => $versionState->status,
                'currency_code' => $versionState->currency_code,
                'exchange_rate' => $versionState->exchange_rate,
                'prices_include_tax' => $versionState->prices_include_tax,
                'notes' => $versionState->notes,
                'created_by' => $versionState->created_by,
            ],
        );

        Quotation::query()->where('state_id', $quotationState->id)->update([
            'active_version_id' => $quotationState->active_version_id,
            'version_count' => $quotationState->version_count,
            'status' => $quotationState->status,
            'charge_excluding_tax_total' => 0,
            'tax_total' => 0,
            'charge_including_tax_total' => 0,
            'charge_total' => 0,
            'deal_total' => null,
            'deal_price_mode' => 'flat_headline',
            'deal_price_original_total' => null,
            'editor_revision' => $quotationState->editor_revision,
        ]);
    }
}
