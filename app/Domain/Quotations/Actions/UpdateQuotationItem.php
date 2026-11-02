<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Quotations\Data\QuotationItemData;
use App\Domain\Quotations\Data\UpdateQuotationItemData;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationAggregateTotals;
use App\Domain\Quotations\Services\QuotationEditingGuard;
use App\Domain\Quotations\Services\QuotationLinePriceCalculator;
use App\Domain\Quotations\Verbs\Events\QuotationItemUpdated;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateQuotationItem
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly QuotationEditingGuard $editingGuard,
        private readonly QuotationLinePriceCalculator $pricing,
        private readonly QuotationAggregateTotals $totals,
    ) {}

    public function __invoke(QuotationItem $item, UpdateQuotationItemData $data): QuotationItemData
    {
        $quotation = $item->quotation()->firstOrFail();
        Gate::authorize('update', $quotation);

        $this->commitVerbs(function () use ($quotation, $item, $data): void {
            $lockedQuotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            $lockedItem = QuotationItem::query()->lockForUpdate()->findOrFail($item->id);
            $lockedVersion = QuotationVersion::query()->lockForUpdate()->findOrFail($lockedItem->version_id);
            $this->editingGuard->assertEditable($lockedQuotation, $lockedVersion);

            $attributes = $data->toArray();
            $name = array_key_exists('name', $attributes) ? trim((string) $attributes['name']) : $lockedItem->name;
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'The item name is required.']);
            }

            $quantity = array_key_exists('quantity', $attributes) ? (string) $attributes['quantity'] : (string) $lockedItem->quantity;
            $startsAt = array_key_exists('starts_at', $attributes) ? $attributes['starts_at'] : $lockedItem->starts_at?->toIso8601String();
            $endsAt = array_key_exists('ends_at', $attributes) ? $attributes['ends_at'] : $lockedItem->ends_at?->toIso8601String();
            $this->validateDates($startsAt, $endsAt);
            $discount = array_key_exists('discount_percent', $attributes) ? $attributes['discount_percent'] : $lockedItem->discount_percent;
            $manualUnitPrice = array_key_exists('unit_price', $attributes)
                ? $attributes['unit_price']
                : ($lockedItem->rate_definition_id === null ? $lockedItem->unit_price : null);
            $product = $lockedItem->isProductBacked()
                ? Product::query()->find($lockedItem->itemable_id)
                : null;
            $pricing = $this->pricing->calculate(
                quotation: $lockedQuotation,
                itemType: $lockedItem->item_type,
                product: $product,
                quantity: $quantity,
                transactionType: $lockedItem->transaction_type,
                manualUnitPrice: $manualUnitPrice,
                discountPercent: $discount !== null ? (string) $discount : null,
                startsAt: $startsAt,
                endsAt: $endsAt,
            );
            $isOptional = array_key_exists('is_optional', $attributes) ? (bool) $attributes['is_optional'] : $lockedItem->is_optional;
            $totals = $this->totals->withLine($lockedVersion, $pricing, $isOptional, [$lockedItem->id]);

            QuotationItemUpdated::fire(
                item_id: $lockedItem->state_id,
                quotation_id: $lockedQuotation->state_id,
                version_id: $lockedVersion->state_id,
                name: $name,
                description: array_key_exists('description', $attributes) ? $attributes['description'] : $lockedItem->description,
                quantity: $quantity,
                unit_price: $pricing->unitPrice,
                rate_definition_id: $pricing->rateDefinitionId,
                total: $pricing->netTotal,
                tax_total: $pricing->taxTotal,
                gross_total: $pricing->grossTotal,
                discount_percent: $discount !== null ? (string) $discount : null,
                is_discount_manual: array_key_exists('discount_percent', $attributes)
                    ? $discount !== null
                    : $lockedItem->is_discount_manual,
                tax_rate: $pricing->taxRate,
                starts_at: $startsAt,
                ends_at: $endsAt,
                is_optional: $isOptional,
                custom_fields: array_key_exists('custom_fields', $attributes) ? $attributes['custom_fields'] : $lockedItem->custom_fields,
                pricing_breakdown: $pricing->breakdown,
                notes: array_key_exists('notes', $attributes) ? $attributes['notes'] : $lockedItem->notes,
                aggregate_net: $totals['net'],
                aggregate_tax: $totals['tax'],
                aggregate_gross: $totals['gross'],
            );
        });

        return QuotationItemData::fromModel($item->refresh());
    }

    private function validateDates(?string $startsAt, ?string $endsAt): void
    {
        if ($startsAt !== null && $endsAt !== null && Carbon::parse($endsAt)->lt(Carbon::parse($startsAt))) {
            throw ValidationException::withMessages(['ends_at' => 'The item end must be after or equal to its start.']);
        }
    }
}
