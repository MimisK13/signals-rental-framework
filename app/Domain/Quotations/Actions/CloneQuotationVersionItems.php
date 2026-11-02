<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Verbs\Events\QuotationItemAdded;
use App\Services\SequenceAllocator;

/** Internal replay-stable snapshot cloning for revisions and alternatives. */
final class CloneQuotationVersionItems
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly SequenceAllocator $sequenceAllocator,
    ) {}

    public function __invoke(Quotation $quotation, QuotationVersion $source, QuotationVersion $target): void
    {
        $this->commitVerbs(function () use ($quotation, $source, $target): void {
            $net = 0;
            $tax = 0;
            $gross = 0;

            foreach ($source->items()->orderBy('path')->get() as $item) {
                $lineNet = $item->deal_price_original_total ?? $item->total;
                $lineTax = $item->deal_price_original_tax_total ?? $item->tax_total;
                $lineGross = $item->deal_price_original_gross_total ?? $item->gross_total;

                if (! $item->is_optional) {
                    $net += $lineNet;
                    $tax += $lineTax;
                    $gross += $lineGross;
                }

                QuotationItemAdded::fire(
                    item_pk: $this->sequenceAllocator->next('quotation_items'),
                    quotation_id: $quotation->state_id,
                    version_id: $target->state_id,
                    quotation_pk: $quotation->id,
                    version_pk: $target->id,
                    itemable_id: $item->itemable_id,
                    itemable_type: $item->itemable_type,
                    item_type: $item->item_type->value,
                    path: $item->path,
                    revenue_group_id: $item->revenue_group_id,
                    name: $item->name,
                    description: $item->description,
                    quantity: (string) $item->quantity,
                    unit_price: $item->unit_price,
                    rate_definition_id: $item->rate_definition_id,
                    charge_period: $item->charge_period->value,
                    total: $lineNet,
                    tax_total: $lineTax,
                    gross_total: $lineGross,
                    currency_code: $item->currency_code,
                    discount_percent: $item->discount_percent !== null ? (string) $item->discount_percent : null,
                    is_discount_manual: $item->is_discount_manual,
                    tax_rate: $item->tax_rate !== null ? (string) $item->tax_rate : null,
                    transaction_type: $item->transaction_type->value,
                    starts_at: $item->starts_at?->toIso8601String(),
                    ends_at: $item->ends_at?->toIso8601String(),
                    is_optional: $item->is_optional,
                    custom_fields: $item->custom_fields,
                    pricing_breakdown: $item->pricing_breakdown,
                    notes: $item->notes,
                    aggregate_net: $net,
                    aggregate_tax: $tax,
                    aggregate_gross: $gross,
                );
            }
        });
    }
}
