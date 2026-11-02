<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Quotations\Enums\QuotationDealPriceMode;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationEditingGuard;
use App\Domain\Quotations\Verbs\Events\QuotationDealPriceChanged;
use App\Domain\Quotations\Verbs\Events\QuotationItemDealPriceChanged;
use Illuminate\Support\Facades\Gate;

final class ClearQuotationDealPrice
{
    use CommitsVerbsEvents;

    public function __construct(private readonly QuotationEditingGuard $editingGuard) {}

    public function __invoke(Quotation $quotation, QuotationVersion $version): Quotation
    {
        Gate::authorize('update', $quotation);

        $this->commitVerbs(function () use ($quotation, $version): void {
            $lockedQuotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $lockedVersion = QuotationVersion::query()->lockForUpdate()->findOrFail($version->id);
            $this->editingGuard->assertDraftVersion($lockedQuotation, $lockedVersion);
            $items = $lockedVersion->items()->orderBy('path')->lockForUpdate()->get();
            $net = 0;
            $tax = 0;
            $gross = 0;

            foreach ($items as $item) {
                $restoredNet = $item->deal_price_original_total ?? $item->total;
                $restoredTax = $item->deal_price_original_tax_total ?? $item->tax_total;
                $restoredGross = $item->deal_price_original_gross_total ?? $item->gross_total;

                if ($item->deal_price_original_total !== null) {
                    QuotationItemDealPriceChanged::fire(
                        item_id: $item->state_id,
                        deal_price_discount_percent: null,
                        deal_price: null,
                        deal_price_original_total: null,
                        deal_price_original_tax_total: null,
                        deal_price_original_gross_total: null,
                        total: $restoredNet,
                        tax_total: $restoredTax,
                        gross_total: $restoredGross,
                    );
                }

                if (! $item->is_optional) {
                    $net += $restoredNet;
                    $tax += $restoredTax;
                    $gross += $restoredGross;
                }
            }

            QuotationDealPriceChanged::fire(
                quotation_id: $lockedQuotation->state_id,
                version_id: $lockedVersion->state_id,
                deal_total: null,
                deal_price_mode: QuotationDealPriceMode::FlatHeadline->value,
                deal_price_original_total: null,
                aggregate_net: $net,
                aggregate_tax: $tax,
                aggregate_gross: $gross,
            );
        });

        return $quotation->refresh();
    }
}
