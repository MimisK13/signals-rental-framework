<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Services;

use App\Domain\Quotations\Enums\QuotationItemType;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class QuotationEditorReadModel
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /** @return array<int, array<string, mixed>> */
    public function tree(Quotation $quotation, ?QuotationVersion $version = null): array
    {
        $version ??= $quotation->activeVersion()->firstOrFail();
        $items = $version->items()->orderBy('path')->orderBy('id')->get();
        $groupsByPath = $items->where('item_type', QuotationItemType::Group)->keyBy('path');
        $duplicates = $this->duplicateIds($items);

        return $items->map(function (QuotationItem $item) use ($groupsByPath, $duplicates, $quotation): array {
            $parentPath = $item->parentPath();
            $productId = $item->isProductBacked() ? (int) $item->itemable_id : null;
            $days = $this->chargeableDays($item, $quotation);
            $available = $this->availability($item, $quotation, $productId);

            return [
                'id' => $item->id,
                'item_type' => $item->item_type->value,
                'path' => $item->path,
                'depth' => $item->depth(),
                'parent_group_id' => $parentPath !== null ? $groupsByPath->get($parentPath)?->id : null,
                'name' => $item->name,
                'description' => $item->description,
                'notes' => $item->notes,
                'quantity' => (string) $item->quantity,
                'days' => $days,
                'unit_price' => $item->unit_price,
                'unit_price_raw' => $item->formatMoneyCost('unit_price'),
                'discount_percent' => $item->discount_percent !== null ? (string) $item->discount_percent : null,
                'is_discount_manual' => $item->is_discount_manual,
                'charge_total' => $item->total,
                'tax_total' => $item->tax_total,
                'gross_total' => $item->gross_total,
                'type_label' => $item->item_type === QuotationItemType::Group ? null : $item->transaction_type->label(),
                'charge_period_label' => $item->charge_period->label(),
                'status_label' => $available === null ? null : ($available ? 'Available' : 'Shortage'),
                'has_shortage' => $available === false,
                'is_optional' => $item->is_optional,
                'product_id' => $productId,
                'product_url' => $productId !== null ? route('products.show', $productId) : null,
                'charge_breakdown' => $item->pricing_breakdown,
                'availability_url' => $productId !== null ? route('availability.index', [
                    'view' => 'gantt',
                    'product' => $productId,
                    'store' => $quotation->store_id,
                    'from' => ($item->starts_at ?? $quotation->starts_at)?->toDateString(),
                    'to' => ($item->ends_at ?? $quotation->ends_at)?->toDateString(),
                ]) : null,
                'starts_at' => $item->starts_at?->toDateString(),
                'ends_at' => $item->ends_at?->toDateString(),
                'has_duplicates' => in_array($item->id, $duplicates, true),
                'planning' => null,
            ];
        })->values()->all();
    }

    /** @return array{quotation_id: int, version_id: int, revision: int, net: int, tax: int, gross: int, currency: string} */
    public function totals(Quotation $quotation, ?QuotationVersion $version = null): array
    {
        $version ??= $quotation->activeVersion()->firstOrFail();

        return [
            'quotation_id' => $quotation->id,
            'version_id' => $version->id,
            'revision' => $quotation->editor_revision,
            'net' => $version->charge_excluding_tax_total,
            'tax' => $version->tax_total,
            'gross' => $version->charge_including_tax_total,
            'currency' => $quotation->currency_code,
        ];
    }

    /**
     * @param  Collection<int, QuotationItem>  $items
     * @return list<int>
     */
    private function duplicateIds(Collection $items): array
    {
        return $items
            ->filter(fn (QuotationItem $item): bool => $item->itemable_id !== null && $item->item_type->isPriceable())
            ->groupBy(fn (QuotationItem $item): string => implode('|', [
                $item->itemable_type,
                $item->itemable_id,
                $item->getRawOriginal('transaction_type'),
                $item->parentPath() ?? 'root',
            ]))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->flatMap(fn (Collection $group): array => $group->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->values()
            ->all();
    }

    private function chargeableDays(QuotationItem $item, Quotation $quotation): int
    {
        $start = $item->starts_at ?? $quotation->starts_at;
        $end = $item->ends_at ?? $quotation->ends_at;

        return $start !== null && $end !== null ? max(1, (int) $start->diffInDays($end)) : 1;
    }

    private function availability(QuotationItem $item, Quotation $quotation, ?int $productId): ?bool
    {
        $start = $item->starts_at ?? $quotation->starts_at;
        $end = $item->ends_at ?? $quotation->ends_at;

        if ($productId === null || $start === null || $end === null) {
            return null;
        }

        return $this->availability->checkAvailability(
            $productId,
            $quotation->store_id,
            Carbon::parse($start),
            Carbon::parse($end),
            max(1, (int) ceil((float) $item->quantity)),
        );
    }
}
