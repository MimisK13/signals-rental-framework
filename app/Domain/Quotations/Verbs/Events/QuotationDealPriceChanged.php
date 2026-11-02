<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Verbs\Events;

use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Verbs\States\QuotationState;
use App\Domain\Quotations\Verbs\States\QuotationVersionState;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

final class QuotationDealPriceChanged extends Event
{
    public function __construct(
        #[StateId(QuotationState::class)] public int $quotation_id,
        #[StateId(QuotationVersionState::class)] public int $version_id,
        public ?int $deal_total,
        public string $deal_price_mode,
        public ?int $deal_price_original_total,
        public int $aggregate_net,
        public int $aggregate_tax,
        public int $aggregate_gross,
    ) {}

    public function applyToQuotation(QuotationState $state): void
    {
        $state->deal_total = $this->deal_total;
        $state->deal_price_mode = $this->deal_price_mode;
        $state->deal_price_original_total = $this->deal_price_original_total;
        $state->charge_excluding_tax_total = $this->aggregate_net;
        $state->tax_total = $this->aggregate_tax;
        $state->charge_including_tax_total = $this->aggregate_gross;
        $state->charge_total = $this->aggregate_net;
        $state->editor_revision++;
    }

    public function applyToVersion(QuotationVersionState $state): void
    {
        $state->charge_excluding_tax_total = $this->aggregate_net;
        $state->tax_total = $this->aggregate_tax;
        $state->charge_including_tax_total = $this->aggregate_gross;
        $state->charge_total = $this->aggregate_net;
    }

    public function handle(QuotationState $quotationState, QuotationVersionState $versionState): void
    {
        $totals = [
            'charge_excluding_tax_total' => $quotationState->charge_excluding_tax_total,
            'tax_total' => $quotationState->tax_total,
            'charge_including_tax_total' => $quotationState->charge_including_tax_total,
            'charge_total' => $quotationState->charge_total,
        ];
        Quotation::query()->where('state_id', $quotationState->id)->update([
            ...$totals,
            'deal_total' => $quotationState->deal_total,
            'deal_price_mode' => $quotationState->deal_price_mode,
            'deal_price_original_total' => $quotationState->deal_price_original_total,
            'editor_revision' => $quotationState->editor_revision,
        ]);
        QuotationVersion::query()->where('state_id', $versionState->id)->update($totals);
    }
}
