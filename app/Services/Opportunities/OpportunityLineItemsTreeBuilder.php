<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Data\Opportunities\OpportunityItemReadContext;
use App\Data\Opportunities\PreparedProductInputs;
use App\Data\Opportunities\PreparedStandardDemandInputs;
use App\Data\Opportunities\PreparedStockInputs;
use App\Domain\Availability\Data\OpportunityItemAvailabilityData;
use App\Domain\Availability\Services\AvailabilityService;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Support\Formatter;
use Illuminate\Support\Collection;

/**
 * Builds the flat pre-order tree read shape consumed by the local-first line-item
 * editor (Alpine store + frozen seed island).
 */
class OpportunityLineItemsTreeBuilder
{
    public function __construct(
        private readonly Formatter $formatter,
        private readonly AvailabilityService $availability,
        private readonly OpportunityEditorTreeService $treeService,
    ) {}

    /**
     * @param  Collection<int, OpportunityItem>|null  $items
     * @param  array<int, OpportunityItemReadContext>  $contexts
     * @param  array<int, int>|null  $availableByItem
     * @return array<int, array<string, mixed>>
     */
    public function tree(
        Opportunity $opportunity,
        ?Collection $items = null,
        array $contexts = [],
        ?PreparedProductInputs $products = null,
        ?PreparedStockInputs $preparedStock = null,
        ?PreparedStandardDemandInputs $preparedDemand = null,
        ?array $availableByItem = null,
    ): array {
        $items = $this->itemsFor($opportunity, $items);
        $groupsByPath = $items
            ->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group)
            ->keyBy('path');
        $pathsWithChildren = $items
            ->map(fn (OpportunityItem $item): ?string => $item->parentPath())
            ->filter()
            ->flip()
            ->all();
        $availability = $availableByItem === null
            ? $this->availabilityMap($opportunity->id, $items, $contexts, $products, $preparedStock, $preparedDemand)
            : [];
        $duplicateIds = $this->duplicateLineIds($items);
        $currencyCode = $opportunity->currency_code ?? settings('company.base_currency', 'GBP');
        $showShortageIndicators = $this->shortageIndicatorsVisible($opportunity);
        $moneyDisplays = [];
        $chargeableDays = [];
        $productUrls = [];
        $availabilityUrls = [];

        return $items
            ->map(function (OpportunityItem $item) use (
                $availability,
                $availableByItem,
                $currencyCode,
                $duplicateIds,
                $groupsByPath,
                $opportunity,
                $pathsWithChildren,
                $showShortageIndicators,
                &$availabilityUrls,
                &$chargeableDays,
                &$moneyDisplays,
                &$productUrls,
            ): array {
                return $this->rowToArray(
                    $item,
                    $pathsWithChildren,
                    $groupsByPath,
                    $availability,
                    $duplicateIds,
                    $opportunity,
                    $currencyCode,
                    $showShortageIndicators,
                    $availableByItem,
                    $moneyDisplays,
                    $chargeableDays,
                    $productUrls,
                    $availabilityUrls,
                );
            })
            ->values()
            ->all();
    }

    /**
     * Destination options for the quick-add bar.
     *
     * @param  Collection<int, OpportunityItem>|null  $items
     * @return array<int, array{value: string, label: string}>
     */
    public function destinations(Opportunity $opportunity, ?Collection $items = null): array
    {
        $options = [['value' => '', 'label' => '— Auto group —']];

        foreach ($this->treeService->buildDisplayGroups($this->itemsFor($opportunity, $items), fn (): array => []) as $group) {
            $prefix = match ($group['kind']) {
                'group' => 'Section · ',
                'auto' => 'Group · ',
                default => '',
            };

            $options[] = [
                'value' => $group['key'],
                'label' => $prefix.$group['label'],
            ];
        }

        return $options;
    }

    /**
     * Parent-group options for the "New section" modal.
     *
     * @param  Collection<int, OpportunityItem>|null  $items
     * @return array<int, array{value: string, label: string}>
     */
    public function parentGroupOptions(Opportunity $opportunity, ?Collection $items = null): array
    {
        return $this->treeService->parentGroupOptions($this->itemsFor($opportunity, $items));
    }

    /**
     * @param  Collection<int, OpportunityItem>|null  $items
     * @return Collection<int, OpportunityItem>
     */
    private function itemsFor(Opportunity $opportunity, ?Collection $items): Collection
    {
        if ($items === null) {
            $opportunity->load([
                'items' => fn ($query) => $query->orderBy('path')->orderBy('id'),
            ]);
            $items = $opportunity->items;
        }

        return $items;
    }

    /**
     * @param  array<string, int>  $pathsWithChildren
     * @param  Collection<string, OpportunityItem>  $groupsByPath
     * @param  array<int, OpportunityItemAvailabilityData>  $availability
     * @param  array<int, bool>  $duplicateIds
     * @param  array<int, int>|null  $availableByItem
     * @param  array<string, string>  $moneyDisplays
     * @param  array<string, int>  $chargeableDays
     * @param  array<int, string>  $productUrls
     * @param  array<int, string>  $availabilityUrls
     * @return array<string, mixed>
     */
    private function rowToArray(
        OpportunityItem $item,
        array $pathsWithChildren,
        Collection $groupsByPath,
        array $availability,
        array $duplicateIds,
        Opportunity $opportunity,
        string $currencyCode,
        bool $showShortageIndicators,
        ?array $availableByItem,
        array &$moneyDisplays,
        array &$chargeableDays,
        array &$productUrls,
        array &$availabilityUrls,
    ): array {
        $depth = $item->depth();
        $parentPath = $item->parentPath();
        $parentGroupId = null;

        if ($parentPath !== null) {
            $parentGroupId = $groupsByPath->get($parentPath)?->id;
        }

        $productId = $this->catalogueProductId($item);
        $avail = $availability[$item->id] ?? null;
        $availabilityState = $availableByItem === null
            ? [
                'label' => $this->availabilityLabel($avail, $showShortageIndicators),
                'status' => $this->availabilityStatus($avail, $showShortageIndicators),
                'has_shortage' => $this->hasShortage($avail),
            ]
            : $this->availabilityStateForQuantity(
                $item,
                $availableByItem[$item->id] ?? null,
                $showShortageIndicators,
            );
        $days = $this->hireDays($item, $opportunity, $chargeableDays);
        $chargeBreakdown = $this->chargeBreakdownFor($item, $currencyCode, $days, $moneyDisplays);

        return [
            'id' => (int) $item->id,
            'item_type' => $item->item_type->value,
            'path' => $item->path,
            'depth' => $depth,
            'parent_path' => $parentPath,
            'parent_group_id' => $parentGroupId,
            'revenue_group_id' => $item->revenue_group_id,
            'name' => $item->name,
            'description' => $item->description,
            'notes' => $item->notes,
            'quantity' => $this->formatQuantity($item->quantity),
            'quantity_raw' => (string) $item->quantity,
            'days' => $days,
            'unit_price' => (int) ($item->unit_price ?? 0),
            'unit_price_display' => $this->moneyDisplay((int) ($item->unit_price ?? 0), $currencyCode, $moneyDisplays),
            'unit_price_raw' => $item->formatMoneyCost('unit_price'),
            'discount_percent' => $item->discount_percent !== null ? (string) $item->discount_percent : null,
            'is_discount_manual' => (bool) $item->is_discount_manual,
            'charge_total' => (int) ($item->total ?? 0),
            'charge_total_display' => $this->moneyDisplay((int) ($item->total ?? 0), $currencyCode, $moneyDisplays),
            'surcharge_total' => (int) ($item->surcharge_total ?? 0),
            'surcharge_total_display' => $this->moneyDisplay((int) ($item->surcharge_total ?? 0), $currencyCode, $moneyDisplays),
            'type_label' => $item->item_type === OpportunityItemType::Group
                ? null
                : ($item->item_type === OpportunityItemType::Text
                    ? OpportunityItemType::Text->label()
                    : $item->transaction_type->label()),
            'status_label' => $availabilityState['label'],
            'availability_status' => $availabilityState['status'],
            'has_shortage' => $showShortageIndicators && $availabilityState['has_shortage'],
            'is_optional' => $item->is_optional,
            'is_collapsed' => false,
            'has_children' => isset($pathsWithChildren[$item->path]),
            'product_id' => $productId,
            'product_url' => $productId !== null
                ? ($productUrls[$productId] ??= route('products.show', $productId))
                : null,
            'charge_breakdown' => $chargeBreakdown,
            'availability_url' => $productId !== null
                ? ($availabilityUrls[$productId] ??= $this->availabilityUrlFor($opportunity, $productId))
                : null,
            'starts_at' => optional($item->starts_at)?->toDateString(),
            'ends_at' => optional($item->ends_at)?->toDateString(),
            'charge_period_label' => $item->charge_period->label(),
            'has_duplicates' => $duplicateIds[$item->id] ?? false,
        ];
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @return array<int, bool>
     */
    private function duplicateLineIds(Collection $items): array
    {
        $byKey = [];

        foreach ($items as $item) {
            if ($item->itemable_id === null || in_array($item->item_type, [OpportunityItemType::Group, OpportunityItemType::Text], true)) {
                continue;
            }

            $key = implode('|', [
                $item->itemable_id,
                $item->itemable_type,
                $item->getRawOriginal('transaction_type'),
                $item->getRawOriginal('charge_period'),
                $item->is_optional ? 1 : 0,
                $item->parentPath() ?? 'null',
                optional($item->starts_at)->toIso8601String() ?? 'null',
                optional($item->ends_at)->toIso8601String() ?? 'null',
            ]);

            $byKey[$key][] = (int) $item->id;
        }

        $flagged = [];

        foreach ($byKey as $ids) {
            if (count($ids) > 1) {
                foreach ($ids as $id) {
                    $flagged[$id] = true;
                }
            }
        }

        return $flagged;
    }

    /**
     * @param  Collection<int, OpportunityItem>|null  $items
     * @param  array<int, OpportunityItemReadContext>  $contexts
     * @return array<int, OpportunityItemAvailabilityData>
     * @return array<int, OpportunityItemAvailabilityData>
     */
    private function availabilityMap(
        int $opportunityId,
        ?Collection $items = null,
        array $contexts = [],
        ?PreparedProductInputs $products = null,
        ?PreparedStockInputs $preparedStock = null,
        ?PreparedStandardDemandInputs $preparedDemand = null,
    ): array {
        if ($items !== null && $products !== null && $preparedStock !== null && $preparedDemand !== null) {
            $resolvedContexts = array_map(
                static fn (OpportunityItemReadContext $context): array => $context->toResolvedArray(),
                $contexts,
            );

            return $this->availability
                ->getOpportunityContext($opportunityId, $items, $resolvedContexts, $products, $preparedStock, $preparedDemand)
                ->keyBy('opportunity_item_id')
                ->all();
        }

        return $this->availability
            ->getOpportunityContext($opportunityId)
            ->keyBy('opportunity_item_id')
            ->all();
    }

    private function shortageIndicatorsVisible(Opportunity $opportunity): bool
    {
        return $opportunity->state === OpportunityState::Order
            || ($opportunity->state === OpportunityState::Quotation
                && $opportunity->statusEnum() === OpportunityStatus::QuotationReserved);
    }

    private function catalogueProductId(OpportunityItem $item): ?int
    {
        if ($item->itemable_id === null || in_array($item->item_type, [OpportunityItemType::Group, OpportunityItemType::Text], true)) {
            return null;
        }

        if ($item->isProductBacked()) {
            return (int) $item->itemable_id;
        }

        $normalizedType = strtolower((string) $item->itemable_type);

        if ($normalizedType === 'product' || str_ends_with($normalizedType, '\\product')) {
            return (int) $item->itemable_id;
        }

        return null;
    }

    private function availabilityLabel(?OpportunityItemAvailabilityData $avail, bool $showShortageIndicators): ?string
    {
        if ($avail === null) {
            return null;
        }

        if ($avail->has_shortage) {
            return $showShortageIndicators ? 'Shortage' : 'Reserved';
        }

        return $avail->available_for_item > 0 ? 'Available' : 'Reserved';
    }

    private function availabilityStatus(?OpportunityItemAvailabilityData $avail, bool $showShortageIndicators): ?string
    {
        if ($avail === null) {
            return null;
        }

        if ($avail->has_shortage) {
            return $showShortageIndicators ? 'out' : 'reserved';
        }

        return $avail->available_for_item > 0 ? 'available' : 'reserved';
    }

    private function hasShortage(?OpportunityItemAvailabilityData $availability): bool
    {
        return $availability !== null && $availability->has_shortage;
    }

    /**
     * @return array{label: string|null, status: string|null, has_shortage: bool}
     */
    private function availabilityStateForQuantity(
        OpportunityItem $item,
        ?int $availableQuantity,
        bool $showShortageIndicators,
    ): array {
        if ($availableQuantity === null) {
            return [
                'label' => null,
                'status' => null,
                'has_shortage' => false,
            ];
        }

        $requestedQuantity = max(1, (int) round((float) $item->quantity));
        $hasShortage = $requestedQuantity > $availableQuantity;

        if ($hasShortage) {
            return [
                'label' => $showShortageIndicators ? 'Shortage' : 'Reserved',
                'status' => $showShortageIndicators ? 'out' : 'reserved',
                'has_shortage' => true,
            ];
        }

        return [
            'label' => 'Available',
            'status' => 'available',
            'has_shortage' => false,
        ];
    }

    private function availabilityUrlFor(Opportunity $opportunity, int $productId): string
    {
        $params = array_filter([
            'view' => 'gantt',
            'product' => $productId,
            'store' => $opportunity->store_id,
            'from' => optional($opportunity->starts_at)?->toDateString(),
            'to' => optional($opportunity->ends_at)?->toDateString(),
        ], fn ($value) => $value !== null && $value !== '');

        return route('availability.index', $params);
    }

    /**
     * @param  array<string, int>  $chargeableDays
     */
    private function hireDays(OpportunityItem $item, Opportunity $opportunity, array &$chargeableDays): int
    {
        $key = implode('|', [
            $opportunity->use_chargeable_days ? 1 : 0,
            $opportunity->chargeable_days ?? 'null',
            $item->getRawOriginal('starts_at') ?? $opportunity->getRawOriginal('starts_at') ?? 'null',
            $item->getRawOriginal('ends_at') ?? $opportunity->getRawOriginal('ends_at') ?? 'null',
        ]);

        return $chargeableDays[$key] ??= app(OpportunityItemChargeableDays::class)->forItem($item, $opportunity);
    }

    /**
     * Per-line charge hover breakdown (CRMS-style). Rental amount is the pre-discount
     * gross for the chargeable window; surcharge reflects persisted line surcharges.
     *
     * @param  array<string, string>  $moneyDisplays
     * @return array<string, string>|null
     */
    private function chargeBreakdownFor(
        OpportunityItem $item,
        string $currencyCode,
        int $days,
        array &$moneyDisplays,
    ): ?array {
        if ($item->item_type === OpportunityItemType::Group) {
            return null;
        }

        $unitPriceMinor = (int) ($item->unit_price ?? 0);
        $quantity = (float) $item->quantity;

        // Sale lines are a one-off charge — they do NOT multiply by the chargeable
        // days, mirroring OpportunityTotalsCalculator::manualLineSubtotal(). Only
        // Rental/Service lines accrue per day.
        $isSale = $item->transaction_type === LineItemTransactionType::Sale;
        $chargeDays = $isSale ? 1 : max(1, $days);
        $rentalMinor = (int) round($quantity * $unitPriceMinor * $chargeDays);
        $surchargeMinor = (int) ($item->surcharge_total ?? 0);

        return [
            'days_line' => sprintf(
                'Days: %s × %d',
                $this->moneyDisplay($unitPriceMinor, $currencyCode, $moneyDisplays),
                $chargeDays,
            ),
            'rental_charge_display' => $this->moneyDisplay($rentalMinor, $currencyCode, $moneyDisplays),
            'surcharge_display' => $this->moneyDisplay($surchargeMinor, $currencyCode, $moneyDisplays),
        ];
    }

    /**
     * @param  array<string, string>  $moneyDisplays
     */
    private function moneyDisplay(int $minorUnits, string $currencyCode, array &$moneyDisplays): string
    {
        $key = $currencyCode."\0".$minorUnits;

        return $moneyDisplays[$key] ??= $this->formatter->money($minorUnits, $currencyCode);
    }

    private function formatQuantity(float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
