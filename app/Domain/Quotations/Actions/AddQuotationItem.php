<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Numbering\Services\SequenceAllocator;
use App\Domain\Products\Models\Product;
use App\Domain\Quotations\Data\AddQuotationItemData;
use App\Domain\Quotations\Data\QuotationItemData;
use App\Domain\Quotations\Enums\QuotationItemType;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationAggregateTotals;
use App\Domain\Quotations\Services\QuotationEditingGuard;
use App\Domain\Quotations\Services\QuotationItemTreeService;
use App\Domain\Quotations\Services\QuotationLinePriceCalculator;
use App\Domain\Quotations\Verbs\Events\QuotationItemAdded;
use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AddQuotationItem
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly QuotationEditingGuard $editingGuard,
        private readonly QuotationItemTreeService $tree,
        private readonly QuotationLinePriceCalculator $pricing,
        private readonly QuotationAggregateTotals $totals,
        private readonly SequenceAllocator $sequenceAllocator,
    ) {}

    public function __invoke(Quotation $quotation, QuotationVersion $version, AddQuotationItemData $data): QuotationItemData
    {
        Gate::authorize('update', $quotation);

        $itemId = $this->commitVerbs(function () use ($quotation, $version, $data): int {
            $lockedQuotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $lockedVersion = QuotationVersion::query()->lockForUpdate()->findOrFail($version->id);
            $this->editingGuard->assertEditable($lockedQuotation, $lockedVersion);

            $itemType = QuotationItemType::from($data->item_type);
            $product = $this->resolveProduct($data->itemable_id, $data->itemable_type, $itemType);
            $parentPath = $this->resolveParentPath($lockedVersion, $data->parent_path, $itemType);
            $path = $parentPath !== null
                ? $this->tree->nextChildPath($lockedVersion->id, $parentPath)
                : $this->tree->nextTopLevelPath($lockedVersion->id);
            $pricing = $this->pricing->calculate(
                quotation: $lockedQuotation,
                itemType: $itemType,
                product: $product,
                quantity: $data->quantity,
                transactionType: LineItemTransactionType::from($data->transaction_type),
                manualUnitPrice: $data->unit_price,
                discountPercent: $data->discount_percent,
                startsAt: $data->starts_at,
                endsAt: $data->ends_at,
            );
            $totals = $this->totals->withLine($lockedVersion, $pricing, $data->is_optional);
            $itemId = $this->sequenceAllocator->next('quotation_items');

            QuotationItemAdded::fire(
                item_pk: $itemId,
                quotation_id: $lockedQuotation->state_id,
                version_id: $lockedVersion->state_id,
                quotation_pk: $lockedQuotation->id,
                version_pk: $lockedVersion->id,
                itemable_id: $product?->id,
                itemable_type: $product !== null ? Product::class : null,
                item_type: $itemType->value,
                path: $path,
                revenue_group_id: $data->revenue_group_id,
                name: $data->name,
                description: $data->description,
                quantity: $data->quantity,
                unit_price: $pricing->unitPrice,
                rate_definition_id: $pricing->rateDefinitionId,
                charge_period: ChargePeriod::from($data->charge_period)->value,
                total: $pricing->netTotal,
                tax_total: $pricing->taxTotal,
                gross_total: $pricing->grossTotal,
                currency_code: $lockedQuotation->currency_code,
                discount_percent: $data->discount_percent,
                is_discount_manual: $data->discount_percent !== null,
                tax_rate: $pricing->taxRate,
                transaction_type: $data->transaction_type,
                starts_at: $data->starts_at,
                ends_at: $data->ends_at,
                is_optional: $data->is_optional,
                custom_fields: $data->custom_fields,
                pricing_breakdown: $pricing->breakdown,
                notes: $data->notes,
                aggregate_net: $totals['net'],
                aggregate_tax: $totals['tax'],
                aggregate_gross: $totals['gross'],
            );

            return $itemId;
        });

        return QuotationItemData::fromModel(QuotationItem::query()->findOrFail($itemId));
    }

    private function resolveProduct(?int $itemableId, ?string $itemableType, QuotationItemType $itemType): ?Product
    {
        if ($itemableId === null) {
            return null;
        }

        if (! in_array($itemType, [QuotationItemType::Product, QuotationItemType::Accessory, QuotationItemType::Service], true)
            || ! in_array($itemableType, [null, 'Product', Product::class], true)) {
            throw ValidationException::withMessages(['itemable_type' => 'Only Product catalogue items are supported.']);
        }

        return Product::query()->findOrFail($itemableId);
    }

    private function resolveParentPath(QuotationVersion $version, ?string $parentPath, QuotationItemType $itemType): ?string
    {
        if ($parentPath === null || $parentPath === '') {
            if ($itemType === QuotationItemType::Accessory) {
                throw ValidationException::withMessages(['parent_path' => 'Accessories must be placed under a product.']);
            }

            return null;
        }

        $parent = $version->items()->where('path', $parentPath)->first();
        $valid = $itemType === QuotationItemType::Accessory
            ? $parent?->item_type === QuotationItemType::Product
            : $parent?->item_type === QuotationItemType::Group;

        if (! $valid) {
            throw ValidationException::withMessages(['parent_path' => 'The selected parent cannot contain this item type.']);
        }

        return $parentPath;
    }
}
