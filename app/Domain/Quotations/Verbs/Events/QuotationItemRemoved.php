<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Verbs\Events;

use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Verbs\States\QuotationItemState;
use App\Domain\Quotations\Verbs\States\QuotationState;
use App\Domain\Quotations\Verbs\States\QuotationVersionState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

final class QuotationItemRemoved extends Event
{
    public function __construct(
        #[StateId(QuotationItemState::class)] public int $item_id,
        #[StateId(QuotationState::class)] public int $quotation_id,
        #[StateId(QuotationVersionState::class)] public int $version_id,
        public int $aggregate_net,
        public int $aggregate_tax,
        public int $aggregate_gross,
    ) {}

    public function applyToItem(QuotationItemState $state): void
    {
        $state->is_deleted = true;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function applyToQuotation(QuotationState $state): void
    {
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

    public function handle(QuotationItemState $itemState, QuotationState $quotationState, QuotationVersionState $versionState): void
    {
        QuotationItem::query()->where('state_id', $itemState->id)->delete();
        $totals = [
            'charge_excluding_tax_total' => $quotationState->charge_excluding_tax_total,
            'tax_total' => $quotationState->tax_total,
            'charge_including_tax_total' => $quotationState->charge_including_tax_total,
            'charge_total' => $quotationState->charge_total,
        ];
        Quotation::query()->where('state_id', $quotationState->id)->update([...$totals, 'editor_revision' => $quotationState->editor_revision]);
        QuotationVersion::query()->where('state_id', $versionState->id)->update($totals);
    }
}
