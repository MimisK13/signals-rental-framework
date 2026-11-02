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

final class QuotationItemAdded extends Event
{
    /**
     * @param  array<string, mixed>|null  $custom_fields
     * @param  array<string, mixed>|null  $pricing_breakdown
     */
    public function __construct(
        public int $item_pk,
        #[StateId(QuotationItemState::class)] public ?int $item_id = null,
        #[StateId(QuotationState::class)] public int $quotation_id = 0,
        #[StateId(QuotationVersionState::class)] public int $version_id = 0,
        public int $quotation_pk = 0,
        public int $version_pk = 0,
        public ?int $itemable_id = null,
        public ?string $itemable_type = null,
        public string $item_type = 'product',
        public string $path = '',
        public ?int $revenue_group_id = null,
        public string $name = '',
        public ?string $description = null,
        public string $quantity = '1.00',
        public int $unit_price = 0,
        public ?int $rate_definition_id = null,
        public int $charge_period = 1,
        public int $total = 0,
        public int $tax_total = 0,
        public int $gross_total = 0,
        public ?string $currency_code = null,
        public ?string $discount_percent = null,
        public bool $is_discount_manual = false,
        public ?string $tax_rate = null,
        public int $transaction_type = 0,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public bool $is_optional = false,
        public ?array $custom_fields = null,
        public ?array $pricing_breakdown = null,
        public ?string $notes = null,
        public int $aggregate_net = 0,
        public int $aggregate_tax = 0,
        public int $aggregate_gross = 0,
    ) {}

    public function applyToItem(QuotationItemState $state): void
    {
        $state->item_id = $this->item_pk;
        $state->quotation_id = $this->quotation_pk;
        $state->version_id = $this->version_pk;
        $state->itemable_id = $this->itemable_id;
        $state->itemable_type = $this->itemable_type;
        $state->item_type = $this->item_type;
        $state->path = $this->path;
        $state->revenue_group_id = $this->revenue_group_id;
        $state->name = $this->name;
        $state->description = $this->description;
        $state->quantity = $this->quantity;
        $state->unit_price = $this->unit_price;
        $state->rate_definition_id = $this->rate_definition_id;
        $state->charge_period = $this->charge_period;
        $state->total = $this->total;
        $state->tax_total = $this->tax_total;
        $state->gross_total = $this->gross_total;
        $state->currency_code = $this->currency_code;
        $state->discount_percent = $this->discount_percent;
        $state->is_discount_manual = $this->is_discount_manual;
        $state->tax_rate = $this->tax_rate;
        $state->transaction_type = $this->transaction_type;
        $state->starts_at = $this->starts_at !== null ? CarbonImmutable::parse($this->starts_at) : null;
        $state->ends_at = $this->ends_at !== null ? CarbonImmutable::parse($this->ends_at) : null;
        $state->is_optional = $this->is_optional;
        $state->custom_fields = $this->custom_fields;
        $state->pricing_breakdown = $this->pricing_breakdown;
        $state->notes = $this->notes;
        $state->is_deleted = false;
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
        QuotationItem::query()->updateOrCreate(['id' => $itemState->item_id], [
            'state_id' => $itemState->id,
            'quotation_id' => $itemState->quotation_id,
            'version_id' => $itemState->version_id,
            'itemable_id' => $itemState->itemable_id,
            'itemable_type' => $itemState->itemable_type,
            'item_type' => $itemState->item_type,
            'path' => $itemState->path,
            'revenue_group_id' => $itemState->revenue_group_id,
            'name' => $itemState->name,
            'description' => $itemState->description,
            'quantity' => $itemState->quantity,
            'unit_price' => $itemState->unit_price,
            'rate_definition_id' => $itemState->rate_definition_id,
            'charge_period' => $itemState->charge_period,
            'total' => $itemState->total,
            'tax_total' => $itemState->tax_total,
            'gross_total' => $itemState->gross_total,
            'currency_code' => $itemState->currency_code,
            'discount_percent' => $itemState->discount_percent,
            'is_discount_manual' => $itemState->is_discount_manual,
            'tax_rate' => $itemState->tax_rate,
            'transaction_type' => $itemState->transaction_type,
            'starts_at' => $itemState->starts_at,
            'ends_at' => $itemState->ends_at,
            'is_optional' => $itemState->is_optional,
            'custom_fields' => $itemState->custom_fields,
            'pricing_breakdown' => $itemState->pricing_breakdown,
            'notes' => $itemState->notes,
        ]);
        $this->projectTotals($quotationState, $versionState);
    }

    private function projectTotals(QuotationState $quotationState, QuotationVersionState $versionState): void
    {
        $totals = [
            'charge_excluding_tax_total' => $quotationState->charge_excluding_tax_total,
            'tax_total' => $quotationState->tax_total,
            'charge_including_tax_total' => $quotationState->charge_including_tax_total,
            'charge_total' => $quotationState->charge_total,
        ];
        Quotation::query()->where('state_id', $quotationState->id)->update([
            ...$totals,
            'editor_revision' => $quotationState->editor_revision,
        ]);
        QuotationVersion::query()->where('state_id', $versionState->id)->update($totals);
    }
}
