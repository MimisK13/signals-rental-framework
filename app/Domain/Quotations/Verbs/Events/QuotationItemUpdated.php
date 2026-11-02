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

final class QuotationItemUpdated extends Event
{
    /**
     * @param  array<string, mixed>|null  $custom_fields
     * @param  array<string, mixed>|null  $pricing_breakdown
     */
    public function __construct(
        #[StateId(QuotationItemState::class)] public int $item_id,
        #[StateId(QuotationState::class)] public int $quotation_id,
        #[StateId(QuotationVersionState::class)] public int $version_id,
        public string $name,
        public ?string $description,
        public string $quantity,
        public int $unit_price,
        public ?int $rate_definition_id,
        public int $total,
        public int $tax_total,
        public int $gross_total,
        public ?string $discount_percent,
        public bool $is_discount_manual,
        public ?string $tax_rate,
        public ?string $starts_at,
        public ?string $ends_at,
        public bool $is_optional,
        public ?array $custom_fields,
        public ?array $pricing_breakdown,
        public ?string $notes,
        public int $aggregate_net,
        public int $aggregate_tax,
        public int $aggregate_gross,
    ) {}

    public function applyToItem(QuotationItemState $state): void
    {
        $state->name = $this->name;
        $state->description = $this->description;
        $state->quantity = $this->quantity;
        $state->unit_price = $this->unit_price;
        $state->rate_definition_id = $this->rate_definition_id;
        $state->total = $this->total;
        $state->tax_total = $this->tax_total;
        $state->gross_total = $this->gross_total;
        $state->discount_percent = $this->discount_percent;
        $state->is_discount_manual = $this->is_discount_manual;
        $state->tax_rate = $this->tax_rate;
        $state->starts_at = $this->starts_at !== null ? CarbonImmutable::parse($this->starts_at) : null;
        $state->ends_at = $this->ends_at !== null ? CarbonImmutable::parse($this->ends_at) : null;
        $state->is_optional = $this->is_optional;
        $state->custom_fields = $this->custom_fields;
        $state->pricing_breakdown = $this->pricing_breakdown;
        $state->notes = $this->notes;
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
        QuotationItem::query()->where('state_id', $itemState->id)->update([
            'name' => $itemState->name,
            'description' => $itemState->description,
            'quantity' => $itemState->quantity,
            'unit_price' => $itemState->unit_price,
            'rate_definition_id' => $itemState->rate_definition_id,
            'total' => $itemState->total,
            'tax_total' => $itemState->tax_total,
            'gross_total' => $itemState->gross_total,
            'discount_percent' => $itemState->discount_percent,
            'is_discount_manual' => $itemState->is_discount_manual,
            'tax_rate' => $itemState->tax_rate,
            'starts_at' => $itemState->starts_at,
            'ends_at' => $itemState->ends_at,
            'is_optional' => $itemState->is_optional,
            'custom_fields' => $itemState->custom_fields,
            'pricing_breakdown' => $itemState->pricing_breakdown,
            'notes' => $itemState->notes,
        ]);
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
