<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Pricing\Services\RoundingStrategy;
use App\Domain\Quotations\Data\SetQuotationDealPriceData;
use App\Domain\Quotations\Enums\QuotationDealPriceMode;
use App\Domain\Quotations\Enums\QuotationItemType;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationEditingGuard;
use App\Domain\Quotations\Verbs\Events\QuotationDealPriceChanged;
use App\Domain\Quotations\Verbs\Events\QuotationItemDealPriceChanged;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SetQuotationDealPrice
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly QuotationEditingGuard $editingGuard,
        private readonly RoundingStrategy $rounding,
    ) {}

    public function __invoke(Quotation $quotation, QuotationVersion $version, SetQuotationDealPriceData $data): Quotation
    {
        Gate::authorize('update', $quotation);

        $this->commitVerbs(function () use ($quotation, $version, $data): void {
            $lockedQuotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $lockedVersion = QuotationVersion::query()->lockForUpdate()->findOrFail($version->id);
            $this->editingGuard->assertDraftVersion($lockedQuotation, $lockedVersion);
            $items = $lockedVersion->items()
                ->where('is_optional', false)
                ->where('item_type', '!=', QuotationItemType::Group->value)
                ->orderBy('path')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['deal_total' => 'Add a priceable item before setting a deal price.']);
            }

            $mode = QuotationDealPriceMode::from($data->deal_price_mode);
            $baselineNet = (int) $items->sum(fn (QuotationItem $item): int => $item->deal_price_original_total ?? $item->total);

            if ($baselineNet <= 0 && $data->deal_total > 0) {
                throw ValidationException::withMessages(['deal_total' => 'A positive deal price requires a positive quotation total.']);
            }

            [$tax, $gross] = $mode === QuotationDealPriceMode::Proportional
                ? $this->applyProportional($items, $data->deal_total, $baselineNet, $lockedQuotation->currency_code)
                : $this->flatTax($items, $data->deal_total, $baselineNet, $lockedQuotation->currency_code);

            QuotationDealPriceChanged::fire(
                quotation_id: $lockedQuotation->state_id,
                version_id: $lockedVersion->state_id,
                deal_total: $data->deal_total,
                deal_price_mode: $mode->value,
                deal_price_original_total: $baselineNet,
                aggregate_net: $data->deal_total,
                aggregate_tax: $tax,
                aggregate_gross: $gross,
            );
        });

        return $quotation->refresh();
    }

    /**
     * @param  Collection<int, QuotationItem>  $items
     * @return array{int, int}
     */
    private function applyProportional(Collection $items, int $target, int $baseline, string $currency): array
    {
        $remaining = $target;
        $taxTotal = 0;
        $grossTotal = 0;
        $lastIndex = $items->count() - 1;

        foreach ($items->values() as $index => $item) {
            $originalNet = $item->deal_price_original_total ?? $item->total;
            $originalTax = $item->deal_price_original_tax_total ?? $item->tax_total;
            $originalGross = $item->deal_price_original_gross_total ?? $item->gross_total;
            $dealNet = $index === $lastIndex
                ? $remaining
                : (int) bcdiv(bcmul((string) $target, (string) $originalNet, 0), (string) max(1, $baseline), 0);
            $remaining -= $dealNet;
            $dealTax = $originalNet > 0
                ? $this->rounding->roundMinorAmount(bcdiv(bcmul((string) $dealNet, (string) $originalTax, 8), (string) $originalNet, 8), $currency)
                : 0;
            $dealGross = $dealNet + $dealTax;
            $discount = $originalNet > 0
                ? bcmul(bcsub('1', bcdiv((string) $dealNet, (string) $originalNet, 12), 12), '100', 10)
                : '0.0000000000';

            QuotationItemDealPriceChanged::fire(
                item_id: $item->state_id,
                deal_price_discount_percent: $discount,
                deal_price: $dealNet,
                deal_price_original_total: $originalNet,
                deal_price_original_tax_total: $originalTax,
                deal_price_original_gross_total: $originalGross,
                total: $dealNet,
                tax_total: $dealTax,
                gross_total: $dealGross,
            );
            $taxTotal += $dealTax;
            $grossTotal += $dealGross;
        }

        return [$taxTotal, $grossTotal];
    }

    /**
     * @param  Collection<int, QuotationItem>  $items
     * @return array{int, int}
     */
    private function flatTax(Collection $items, int $target, int $baseline, string $currency): array
    {
        $baselineTax = (int) $items->sum(fn (QuotationItem $item): int => $item->deal_price_original_tax_total ?? $item->tax_total);
        $tax = $baseline > 0
            ? $this->rounding->roundMinorAmount(bcdiv(bcmul((string) $target, (string) $baselineTax, 8), (string) $baseline, 8), $currency)
            : 0;

        return [$tax, $target + $tax];
    }
}
