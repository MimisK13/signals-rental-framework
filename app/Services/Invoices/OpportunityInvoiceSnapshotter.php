<?php

namespace App\Services\Invoices;

use App\Data\Invoices\FrozenInvoiceLineData;
use App\Data\Tax\DocumentTaxLineInput;
use App\Enums\DealPriceMode;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Services\TaxCalculator;
use Illuminate\Support\Collection;

/**
 * Pulls the ACTIVE quote version's lines and costs into immutable invoice line
 * payloads, respecting version scoping via {@see Opportunity::items()}.
 */
class OpportunityInvoiceSnapshotter
{
    public function __construct(
        private readonly TaxCalculator $taxCalculator,
    ) {}

    /**
     * @return array{
     *     lines: list<FrozenInvoiceLineData>,
     *     tax_lines: list<DocumentTaxLineInput>,
     *     net_headline: int,
     * }
     */
    public function snapshot(Opportunity $opportunity): array
    {
        $opportunity->loadMissing([
            'items.item',
            'items.itemSurcharges.surcharge',
            'costs',
            'member',
        ]);

        $currency = $this->currencyFor($opportunity);
        $defaultProductTaxClassId = $this->defaultProductTaxClassId();
        $orgTaxClassId = $opportunity->member?->sale_tax_class_id;

        $productIds = $opportunity->items
            ->filter(fn (OpportunityItem $item): bool => $item->isProductBacked())
            ->pluck('itemable_id')
            ->filter()
            ->unique()
            ->all();

        /** @var Collection<int, Product> $productsById */
        $productsById = $productIds === []
            ? collect()
            : Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        $displayLines = [];
        $taxLines = [];
        $excludingTax = 0;
        $sortOrder = 0;

        foreach ($opportunity->items as $item) {
            if ($item->is_optional || ! $item->item_type->isPriceable()) {
                continue;
            }

            $product = $item->isProductBacked()
                ? $productsById->get((int) $item->itemable_id)
                : null;

            $lineNet = (int) $item->total;
            $excludingTax += $lineNet + (int) $item->surcharge_total;

            $lineKey = 'item:'.$item->id;
            $taxLines[] = new DocumentTaxLineInput(
                lineKey: $lineKey,
                netMinor: $lineNet,
                productTaxClassId: $product?->tax_class_id,
                quantity: max(0, (int) round((float) $item->quantity)),
            );

            $displayLines[] = $this->buildItemLine(
                lineKey: $lineKey,
                item: $item,
                product: $product,
                netTotal: $lineNet,
                sortOrder: $sortOrder++,
            );

            foreach ($item->itemSurcharges as $itemSurcharge) {
                if ($itemSurcharge->is_disabled) {
                    continue;
                }

                $surchargeNet = (int) $itemSurcharge->amount;
                $surchargeKey = 'item:'.$item->id.':surcharge:'.$itemSurcharge->surcharge_id;

                $taxLines[] = new DocumentTaxLineInput(
                    lineKey: $surchargeKey,
                    netMinor: $surchargeNet,
                    productTaxClassId: $itemSurcharge->product_tax_class_id,
                    quantity: max(0, (int) round((float) $item->quantity)),
                );

                $displayLines[] = new FrozenInvoiceLineData(
                    invoice_item_id: 0,
                    line_key: $surchargeKey,
                    source_type: 'surcharge',
                    description: $itemSurcharge->surcharge->name ?? 'Surcharge',
                    quantity: $item->quantity,
                    unit_price: $surchargeNet,
                    discount: 0,
                    net_total: $surchargeNet,
                    tax: 0,
                    product_tax_class_id: (int) ($itemSurcharge->product_tax_class_id ?? $defaultProductTaxClassId ?? 0),
                    tax_treatment: 'standard',
                    opportunity_item_id: (int) $item->id,
                    product_id: $product?->id,
                    surcharge_id: (int) $itemSurcharge->surcharge_id,
                    section_item_id: $this->sectionItemId($item),
                    sort_order: $sortOrder++,
                );
            }
        }

        foreach ($opportunity->costs as $cost) {
            if ($cost->is_optional) {
                continue;
            }

            $costNet = $this->costNet($cost, $opportunity, $defaultProductTaxClassId);
            $excludingTax += $costNet;
            $costKey = 'cost:'.$cost->id;

            $taxLines[] = new DocumentTaxLineInput(
                lineKey: $costKey,
                netMinor: $costNet,
                productTaxClassId: $defaultProductTaxClassId,
                quantity: max(0, (int) round((float) $cost->quantity)),
            );

            $displayLines[] = new FrozenInvoiceLineData(
                invoice_item_id: 0,
                line_key: $costKey,
                source_type: 'cost',
                description: $cost->description,
                quantity: $cost->quantity,
                unit_price: (int) $cost->amount,
                discount: 0,
                net_total: $costNet,
                tax: 0,
                product_tax_class_id: (int) ($defaultProductTaxClassId ?? 0),
                tax_treatment: 'standard',
                opportunity_cost_id: (int) $cost->id,
                sort_order: $sortOrder++,
            );
        }

        $dealMode = DealPriceMode::tryFrom((string) ($opportunity->deal_price_mode ?? ''))
            ?? DealPriceMode::FlatHeadline;

        $netHeadline = match (true) {
            $opportunity->deal_total !== null && $dealMode === DealPriceMode::FlatHeadline => (int) $opportunity->deal_total,
            default => $excludingTax,
        };

        if ($opportunity->deal_total !== null && $dealMode === DealPriceMode::FlatHeadline) {
            $taxLines = [
                new DocumentTaxLineInput(
                    lineKey: 'deal-headline',
                    netMinor: $netHeadline,
                    productTaxClassId: $defaultProductTaxClassId,
                ),
            ];
        }

        return [
            'lines' => $displayLines,
            'tax_lines' => $taxLines,
            'net_headline' => $netHeadline,
        ];
    }

    private function buildItemLine(
        string $lineKey,
        OpportunityItem $item,
        ?Product $product,
        int $netTotal,
        int $sortOrder,
    ): FrozenInvoiceLineData {
        $quantityUnits = max(0, (int) round((float) $item->quantity));
        $grossBeforeDiscount = (int) $item->unit_price * max(1, $quantityUnits);
        $discount = max(0, $grossBeforeDiscount - $netTotal);

        return new FrozenInvoiceLineData(
            invoice_item_id: 0,
            line_key: $lineKey,
            source_type: 'item',
            description: $item->name,
            quantity: $item->quantity,
            unit_price: (int) $item->unit_price,
            discount: $discount,
            net_total: $netTotal,
            tax: 0,
            product_tax_class_id: $product !== null ? (int) $product->tax_class_id : 0,
            tax_treatment: 'standard',
            opportunity_item_id: (int) $item->id,
            product_id: $product?->id,
            section_item_id: $this->sectionItemId($item),
            sort_order: $sortOrder,
        );
    }

    private function sectionItemId(OpportunityItem $item): ?int
    {
        if ($item->item_type === OpportunityItemType::Group) {
            return (int) $item->id;
        }

        $path = trim($item->path, '/');
        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);
        if (count($segments) < 2) {
            return null;
        }

        return (int) $segments[0];
    }

    private function costNet(OpportunityCost $cost, Opportunity $opportunity, ?int $defaultProductTaxClassId): int
    {
        $quantityUnits = max(0, (int) round((float) $cost->quantity));
        $lineTotal = $cost->amount * $quantityUnits;

        if (! $opportunity->prices_include_tax) {
            return $lineTotal;
        }

        return $this->taxCalculator->calculateInclusive(
            $lineTotal,
            $this->currencyFor($opportunity),
            $opportunity->member?->sale_tax_class_id,
            $defaultProductTaxClassId,
        )->netAmount;
    }

    private function defaultProductTaxClassId(): ?int
    {
        return ProductTaxClass::query()->where('is_default', true)->value('id');
    }

    private function currencyFor(Opportunity $opportunity): string
    {
        $code = $opportunity->currency_code;

        return is_string($code) && $code !== '' ? $code : 'GBP';
    }
}
