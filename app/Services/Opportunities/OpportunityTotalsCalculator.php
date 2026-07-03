<?php

namespace App\Services\Opportunities;

use App\Data\Tax\DocumentTaxLineInput;
use App\Data\Tax\DocumentTaxRequest;
use App\Enums\DealPriceMode;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityCostType;
use App\Enums\RateTransactionType;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Services\CurrencyService;
use App\Services\Pricing\BundlePricingApplicator;
use App\Services\Pricing\DiscountSourceRegistry;
use App\Services\Pricing\OpportunityItemDiscountPercentFilterRegistry;
use App\Services\Pricing\OpportunitySurchargeApplicator;
use App\Services\Pricing\QuantityMinorArithmetic;
use App\Services\Pricing\RoundingStrategy;
use App\Services\RateEngine\RateCalculator;
use App\Services\RateEngine\RateResolver;
use App\Services\Tax\DocumentTaxBreakdown;
use App\Services\Tax\TaxPointDateResolver;
use App\Services\Tax\TaxResolutionContext;
use App\Services\Tax\TaxResolutionContextHolder;
use App\Services\TaxCalculator;
use App\ValueObjects\CalculationContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Derives the priced totals for opportunity line items and rolls them up onto the
 * parent opportunity, using the shared rate + tax engines.
 *
 * TAX MODEL (Ben's directive — "all totals ex-tax, taxes calculated finally"):
 * every stored money total is NET (tax-exclusive). Tax is NEVER applied per-line
 * during the build; it is computed in a single FINAL pass at rollup time by
 * grouping the net amounts by their effective tax class and applying each group's
 * rate once. The only with-tax figure stored is `charge_including_tax_total`,
 * derived at the very end as `charge_total (net) + tax_total`.
 *
 * A line's NET (tax-exclusive) total is resolved as:
 *
 *   1. resolve a per-unit price — a non-null manual override always wins; else
 *      the matched {@see ProductRate} price; else 0 when nothing matches.
 *   2. derive the subtotal — for a rate-backed line the {@see RateCalculator}
 *      produces the duration-aware subtotal (via the rate definition's strategy,
 *      base period and modifiers); for a manual/no-rate line the subtotal is
 *      `unit_price * quantity * chargeable_days` for hire-based transaction
 *      types (the same day count the editor shows), or `unit_price * quantity`
 *      for sale lines — quantity is a decimal string via {@see QuantityMinorArithmetic}.
 *   3. apply the line discount (tenant rounding at the minor-unit boundary, BEFORE tax).
 *   4. NET EXTRACTION when `prices_include_tax`: an input entered tax-inclusive has
 *      its embedded tax stripped at this point ({@see TaxCalculator::calculateInclusive})
 *      so the stored `opportunity_items.total` is always NET regardless of input
 *      mode. The final rollup tax pass then re-derives tax additively on the net.
 *
 * The discounted NET is stored on `opportunity_items.total`. The line's resolved
 * `tax_rate` is recorded for display/reporting only — it is NOT used to store a
 * taxed line total.
 *
 * GROUPED FINAL TAX: {@see rollUp()} delegates to {@see DocumentTaxBreakdown}
 * which buckets every non-optional line's and cost's net by effective product tax
 * class, taxes each group once at the minor-unit boundary, then reconciles
 * per-line allocations via largest-remainder so Σ line tax ≡ Σ group tax.
 *
 * Both methods are idempotent (they overwrite), so they are safe to run inside an
 * event handle() and reproduce identical totals on replay. They write projection
 * rows quietly (updateQuietly / saveQuietly) so no model events fire and no
 * further Verbs events are triggered.
 *
 * LOCKING (multi-currency-tax-engine.md §4.3/§7.2): once an opportunity is
 * confirmed as an order it carries `exchange_rate_locked` + `tax_locked`. This
 * calculator works entirely in the opportunity's stored minor-unit currency and
 * NEVER fetches a live exchange rate, so `exchange_rate_locked` is already honoured
 * by construction (the stored `exchange_rate` snapshot is authoritative).
 * `tax_locked` is honoured explicitly: {@see rollUp()} skips the final grouped tax
 * pass and preserves the stored `tax_total` / `charge_including_tax_total`, so a
 * later tax-rule or rate change can never move a confirmed order's tax.
 *
 * What the locks freeze is the RATE and TAX TREATMENT, not the line set: structural
 * edits (adding, removing or re-dating lines) on a locked order still recompute the
 * net `charge_total` — but always at the frozen `exchange_rate` snapshot, and (while
 * `tax_locked`) without re-deriving tax. So a locked order's headline can still move
 * when its lines genuinely change; what can never move it is a later FX-rate or
 * tax-rule change re-pricing the already-agreed basis.
 */
class OpportunityTotalsCalculator
{
    public function __construct(
        private readonly RateResolver $rateResolver,
        private readonly RateCalculator $rateCalculator,
        private readonly TaxCalculator $taxCalculator,
        private readonly OpportunityItemChargeableDays $chargeableDays,
        private readonly RoundingStrategy $roundingStrategy,
        private readonly QuantityMinorArithmetic $quantityMinor,
        private readonly DocumentTaxBreakdown $documentTaxBreakdown,
        private readonly TaxPointDateResolver $taxPointDateResolver,
        private readonly TaxResolutionContextHolder $taxResolutionContextHolder,
        private readonly BundlePricingApplicator $bundlePricingApplicator,
        private readonly OpportunitySurchargeApplicator $surchargeApplicator,
    ) {}

    /**
     * Resolve the currency a calculation runs in: the opportunity's snapshotted
     * currency, falling back to the company base-currency setting (never a
     * hardcoded 'GBP' literal — the 'GBP' here is only the setting's own default
     * when entirely unconfigured) so non-base-currency opportunities tax at the
     * right minor-unit scale.
     */
    private function currencyFor(Opportunity $opportunity): string
    {
        $code = $opportunity->currency_code;

        if (is_string($code) && $code !== '') {
            return $code;
        }

        $base = app(CurrencyService::class)->baseCurrencyCode();

        return $base !== '' ? $base : 'GBP';
    }

    /**
     * Recompute and persist the priced fields (`unit_price`, `total`, `tax_rate`)
     * for a single line item. The stored `total` is always NET. Does NOT touch the
     * parent — call {@see rollUp()} afterwards.
     *
     * `$manualUnitPrice` is the event-sourced manual override (from
     * {@see OpportunityItemState::$manual_unit_price}). When non-null it always
     * wins over the resolved rate; null defers to the rate engine. Passing it
     * explicitly keeps the override authoritative and replay-stable (the price-
     * overriding event carries it in its payload).
     */
    public function recalculateItem(OpportunityItem $item, ?int $manualUnitPrice = null): void
    {
        $opportunity = $item->opportunity()->first();

        if ($opportunity === null) {
            return;
        }

        $this->repriceOpportunity($opportunity, $item, $manualUnitPrice, rollUp: false);
    }

    /**
     * Reprice every line on the opportunity through the staged pricing pipeline:
     *
     *   Stage 1   — base rate resolution (per-unit price × quantity/duration)
     *   Stage 1.5 — bundle pricing (group-level fixed / percent bundles)
     *   Stage 2   — line discount percent (post-bundle, pre-surcharge)
     *   Stage 3   — surcharges on the discounted net
     *
     * Bundle and aggregate quantity discounts require opportunity-wide context,
     * so even a single-line mutation reprices the full opportunity item set.
     */
    public function repriceOpportunity(
        Opportunity $opportunity,
        ?OpportunityItem $focusItem = null,
        ?int $manualUnitPrice = null,
        bool $rollUp = true,
    ): void {
        $opportunity->loadMissing(['items.item', 'member']);
        $items = $opportunity->items;
        $currency = $this->currencyFor($opportunity);
        $orgTaxClassId = $opportunity->member?->sale_tax_class_id;

        /** @var array<int, int> $baseSubtotals */
        $baseSubtotals = [];

        /** @var array<int, array{unit_price: int, product: ?Product}> $itemMeta */
        $itemMeta = [];

        foreach ($items as $item) {
            if (! $item->item_type->isPriceable()) {
                continue;
            }

            $manual = ($focusItem !== null && (int) $focusItem->id === (int) $item->id)
                ? $manualUnitPrice
                : null;

            $quantity = $this->quantityMinor->normalizeQuantity((string) $item->quantity);
            [$start, $end] = $this->effectiveDates($item, $opportunity);
            $product = $this->resolveProduct($item);

            [$unitPrice, $netSubtotal] = $this->resolveSubtotal(
                $item,
                $product,
                $currency,
                $quantity,
                $start,
                $end,
                $opportunity->store_id,
                $manual,
                $opportunity,
            );

            $baseSubtotals[(int) $item->id] = $netSubtotal;
            $itemMeta[(int) $item->id] = [
                'unit_price' => $unitPrice,
                'product' => $product,
            ];
        }

        $preDiscountByItem = $this->bundlePricingApplicator->apply(
            $opportunity,
            $items,
            $baseSubtotals,
            $currency,
        );

        foreach ($items as $item) {
            if (! isset($itemMeta[(int) $item->id])) {
                continue;
            }

            $meta = $itemMeta[(int) $item->id];
            $preDiscount = $preDiscountByItem[(int) $item->id] ?? 0;
            $categoryDiscounted = $this->applyDiscount(
                $preDiscount,
                $this->effectiveDiscountPercent($item, $opportunity),
                $currency,
            );
            $discounted = $this->applyDiscount(
                $categoryDiscounted,
                $item->deal_price_discount_percent !== null ? (string) $item->deal_price_discount_percent : null,
                $currency,
            );

            $net = $opportunity->prices_include_tax
                ? $this->taxCalculator->calculateInclusive(
                    $discounted,
                    $currency,
                    $orgTaxClassId,
                    $meta['product']?->tax_class_id,
                )->netAmount
                : $discounted;

            $surchargeResult = $this->surchargeApplicator->applyToItem(
                $item,
                $opportunity,
                $net,
                $currency,
                $meta['product'],
            );

            $taxRate = $this->taxCalculator
                ->calculate($net, $currency, $orgTaxClassId, $meta['product']?->tax_class_id)
                ->ratePercentage;

            $item->forceFill([
                'unit_price' => $meta['unit_price'],
                'total' => $net,
                'surcharge_total' => $surchargeResult['surcharge_total'],
                'tax_rate' => $taxRate,
                'currency_code' => $currency,
            ])->saveQuietly();
        }

        if ($rollUp) {
            $this->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));
        }
    }

    /**
     * Recompute and persist the resolved `tax_rate` for a single cost. Does NOT
     * touch the parent — call {@see rollUp()} afterwards.
     *
     * Costs are NOT priced by the rate engine — the operator-supplied `amount`
     * stands as the per-unit charge. Only the display `tax_rate` is resolved here
     * (against the opportunity member's tax class + the default product tax class,
     * since a cost has no product). The stored `amount` is the per-unit NET; the
     * net rollup bucket is `amount * quantity` (with inclusive extraction applied
     * at rollup time if `prices_include_tax`).
     */
    public function recalculateCost(OpportunityCost $cost): void
    {
        $opportunity = $cost->opportunity()->first();

        if ($opportunity === null) {
            return;
        }

        $currency = $this->currencyFor($opportunity);
        $defaultProductTaxClassId = $this->defaultProductTaxClassId();

        $taxRate = $this->taxCalculator->calculate(
            $this->costNet($cost, $opportunity, $defaultProductTaxClassId),
            $currency,
            $opportunity->member?->sale_tax_class_id,
            $defaultProductTaxClassId,
        )->ratePercentage;

        $cost->forceFill([
            'tax_rate' => $taxRate,
            'currency_code' => $currency,
        ])->saveQuietly();
    }

    /**
     * Reload the opportunity's non-removed items AND costs, sum the per-type NET
     * charge totals (optional lines/costs excluded), compute the tax in a single
     * GROUPED FINAL pass, and persist all the RMS charge totals onto the
     * projection row.
     *
     * All per-type and headline totals are NET. The net amounts are bucketed by
     * effective tax class while they are summed; once every net total is known, the
     * tax for each group is computed once and the results summed into `tax_total`.
     * `charge_including_tax_total` is then `charge_total (net) + tax_total`.
     *
     * Items are priced by the rate engine (their NET is read from the stored
     * `opportunity_items.total`); costs carry their own NET `amount`. Cost net is
     * routed by cost type into the transit / loss-damage / service buckets.
     */
    public function rollUp(Opportunity $opportunity): void
    {
        $items = $opportunity->items()->with('itemSurcharges')->get();
        $costs = $opportunity->costs()->get();

        // Batch-fetch every product-backed line's product once, keyed by id, so the
        // per-item loop below resolves the product from memory instead of issuing
        // one SELECT per line (an N+1 on every totals rollup).
        $productIds = $items
            ->filter(fn (OpportunityItem $item): bool => $item->isProductBacked())
            ->pluck('itemable_id')
            ->filter()
            ->unique()
            ->all();

        /** @var Collection<int, Product> $productsById */
        $productsById = $productIds === []
            ? collect()
            : Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        $rental = 0;
        $sale = 0;
        $service = 0;
        $transit = 0;
        $lossDamage = 0;
        $excludingTax = 0;

        $orgTaxClassId = $opportunity->member?->sale_tax_class_id;
        $currency = $this->currencyFor($opportunity);
        $defaultProductTaxClassId = $this->defaultProductTaxClassId();

        /** @var list<DocumentTaxLineInput> $taxLines */
        $taxLines = [];

        foreach ($items as $item) {
            if ($item->is_optional) {
                continue;
            }

            $product = $item->isProductBacked()
                ? $productsById->get((int) $item->itemable_id)
                : null;
            $lineNet = (int) $item->total;
            $surchargeNet = (int) $item->surcharge_total;
            $lineChargeNet = $lineNet + $surchargeNet;

            $excludingTax += $lineChargeNet;
            $taxLines[] = new DocumentTaxLineInput(
                lineKey: 'item:'.$item->id,
                netMinor: $lineNet,
                productTaxClassId: $product?->tax_class_id,
                quantity: bccomp((string) $item->quantity, '0', 2) <= 0 ? '0' : bcadd((string) $item->quantity, '0', 2),
            );

            foreach ($item->itemSurcharges as $itemSurcharge) {
                if ($itemSurcharge->is_disabled) {
                    continue;
                }

                $taxLines[] = new DocumentTaxLineInput(
                    lineKey: 'item:'.$item->id.':surcharge:'.$itemSurcharge->surcharge_id,
                    netMinor: (int) $itemSurcharge->amount,
                    productTaxClassId: $itemSurcharge->product_tax_class_id,
                    quantity: bccomp((string) $item->quantity, '0', 2) <= 0 ? '0' : bcadd((string) $item->quantity, '0', 2),
                );
            }

            match ($item->transaction_type) {
                LineItemTransactionType::Sale => $sale += $lineChargeNet,
                LineItemTransactionType::Service => $service += $lineChargeNet,
                // Rental only — SubRental is rejected at the input boundary
                // ({@see AddOpportunityItemData}), so no line ever reaches this
                // branch as SubRental, keeping the `sub_rental_charge_total => 0`
                // stub below correct until the Phase 4 sub-hire path exists.
                default => $rental += $lineChargeNet,
            };
        }

        foreach ($costs as $cost) {
            if ($cost->is_optional) {
                continue;
            }

            $costNet = $this->costNet($cost, $opportunity, $defaultProductTaxClassId);

            $excludingTax += $costNet;
            $taxLines[] = new DocumentTaxLineInput(
                lineKey: 'cost:'.$cost->id,
                netMinor: $costNet,
                productTaxClassId: $defaultProductTaxClassId,
                quantity: bccomp((string) $cost->quantity, '0', 2) <= 0 ? '0' : bcadd((string) $cost->quantity, '0', 2),
            );

            // Route the cost net into the RMS category bucket; delivery feeds the
            // transit total, loss/damage feeds its own total, everything else the
            // general service total.
            match ($cost->cost_type) {
                OpportunityCostType::Delivery => $transit += $costNet,
                OpportunityCostType::LossDamage => $lossDamage += $costNet,
                default => $service += $costNet,
            };
        }

        // A deal-total override is a NET override of the headline (Ben: the deal
        // price is net of tax). Flat headline mode replaces the headline while
        // leaving per-line nets untouched; proportional mode sums adjusted lines.
        $dealMode = DealPriceMode::tryFrom((string) ($opportunity->deal_price_mode ?? ''))
            ?? DealPriceMode::FlatHeadline;

        $netHeadline = match (true) {
            $opportunity->deal_total !== null && $dealMode === DealPriceMode::FlatHeadline => (int) $opportunity->deal_total,
            default => $excludingTax,
        };

        if ($opportunity->tax_locked) {
            // TAX LOCK (MC §7.2): the opportunity is confirmed — preserve the tax
            // figures exactly as they were locked. The net buckets above still
            // reflect the current lines, but the final tax pass is skipped so a
            // later tax-rule/rate change cannot move a confirmed order's tax.
            $taxTotal = (int) $opportunity->tax_total;
        } else {
            $taxContext = TaxResolutionContext::fromOpportunity($opportunity);
            $taxPointDate = $this->taxPointDateResolver->resolveForOpportunity($opportunity);

            $taxTotal = $this->taxResolutionContextHolder->using($taxContext, function () use (
                $opportunity,
                $taxLines,
                $currency,
                $orgTaxClassId,
                $netHeadline,
                $defaultProductTaxClassId,
                $dealMode,
                $taxPointDate,
            ): int {
                if ($opportunity->deal_total !== null && $dealMode === DealPriceMode::FlatHeadline) {
                    return $this->documentTaxBreakdown->summarize(new DocumentTaxRequest(
                        lines: [
                            new DocumentTaxLineInput(
                                lineKey: 'deal-headline',
                                netMinor: $netHeadline,
                                productTaxClassId: $defaultProductTaxClassId,
                            ),
                        ],
                        currencyCode: $currency,
                        organisationTaxClassId: $orgTaxClassId,
                        effectiveAt: $taxPointDate,
                        pricesIncludeTax: $opportunity->prices_include_tax,
                    ))->totalTax;
                }

                return $this->documentTaxBreakdown->summarize(new DocumentTaxRequest(
                    lines: $taxLines,
                    currencyCode: $currency,
                    organisationTaxClassId: $orgTaxClassId,
                    effectiveAt: $taxPointDate,
                    pricesIncludeTax: false,
                ))->totalTax;
            });
        }

        $opportunity->forceFill([
            'rental_charge_total' => $rental,
            'sale_charge_total' => $sale,
            'service_charge_total' => $service,
            // Sub-hire is a Phase 4 deliverable — no source populates this yet, and
            // SubRental line items are rejected at the input boundary, so 0 is safe.
            'sub_rental_charge_total' => 0,
            'transit_charge_total' => $transit,
            'loss_damage_charge_total' => $lossDamage,
            // All headline totals are NET. charge_total == charge_excluding_tax_total.
            'charge_excluding_tax_total' => $netHeadline,
            'tax_total' => $taxTotal,
            'charge_including_tax_total' => $netHeadline + $taxTotal,
            'charge_total' => $netHeadline,
        ])->saveQuietly();

        // When the opportunity carries an active quote version, mirror the
        // just-computed NET totals onto that version row so its snapshot stays
        // current (replay-active — projection only).
        $this->syncActiveVersionTotals($opportunity, $netHeadline, $taxTotal);
    }

    /**
     * Mirror the opportunity's headline NET totals onto its active quote version
     * row (if any), so the version's stored snapshot tracks its scoped items.
     * No-op for non-versioned opportunities (`active_version_id = 0`).
     */
    private function syncActiveVersionTotals(Opportunity $opportunity, int $netHeadline, int $taxTotal): void
    {
        if ($opportunity->active_version_id <= 0) {
            return;
        }

        OpportunityVersion::query()
            ->whereKey($opportunity->active_version_id)
            ->update([
                'charge_excluding_tax_total' => $netHeadline,
                'tax_total' => $taxTotal,
                'charge_including_tax_total' => $netHeadline + $taxTotal,
                'charge_total' => $netHeadline,
            ]);
    }

    /**
     * The cost's NET line value in minor units: amount × quantity (decimal-safe),
     * embedded tax stripped when prices are entered tax-inclusive so the bucket
     * stays net.
     */
    private function costNet(OpportunityCost $cost, Opportunity $opportunity, ?int $defaultProductTaxClassId): int
    {
        $currency = $this->currencyFor($opportunity);
        $lineTotal = $this->quantityMinor->multiplyMinorByQuantity(
            (int) $cost->amount,
            (string) $cost->quantity,
            $currency,
        );

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

    /**
     * Resolve the id of the default product tax class (costs have no product, so
     * they tax against the default class). Null when no default is configured,
     * which yields zero-tax — the same outcome as an untaxed line.
     */
    private function defaultProductTaxClassId(): ?int
    {
        return ProductTaxClass::query()->where('is_default', true)->value('id');
    }

    /**
     * Resolve the per-unit price and the NET subtotal (pre-discount) for a line.
     *
     * @return array{0: int, 1: int} [unitPrice, netSubtotal] in minor units
     */
    private function resolveSubtotal(
        OpportunityItem $item,
        ?Product $product,
        string $currency,
        string $quantity,
        Carbon $start,
        Carbon $end,
        ?int $storeId,
        ?int $manualUnitPrice,
        Opportunity $opportunity,
    ): array {
        // A manual override always wins over the rate engine.
        if ($manualUnitPrice !== null) {
            return [
                $manualUnitPrice,
                $this->manualLineSubtotal($manualUnitPrice, $quantity, $item, $opportunity, $currency),
            ];
        }

        $priceCategoryId = $opportunity->member?->price_category_id;

        $rate = $product !== null
            ? $this->rateResolver->resolve(
                $product,
                $this->mapTransactionType($item->transaction_type),
                $storeId,
                $start,
                $priceCategoryId,
            )
            : null;

        if ($rate === null || $rate->rateDefinition === null) {
            // No rate matched: fall back to whatever unit price is on the line
            // (0 for a fresh ad-hoc line) times quantity and chargeable days.
            $unitPrice = $item->unit_price;

            return [
                $unitPrice,
                $this->manualLineSubtotal($unitPrice, $quantity, $item, $opportunity, $currency),
            ];
        }

        if ($this->quantityMinor->quantityHasFraction($quantity)) {
            return [
                $rate->price,
                $this->manualLineSubtotal($rate->price, $quantity, $item, $opportunity, $currency),
            ];
        }

        $definition = $rate->rateDefinition;
        $wholeUnits = $this->quantityMinor->wholeUnitCountForRateEngine($quantity);

        $context = new CalculationContext(
            unitPriceMinor: $rate->price,
            currency: $currency,
            start: $start,
            end: $end,
            quantity: max(1, $wholeUnits),
            basePeriod: $definition->base_period,
            strategyConfig: $definition->strategy_config,
            transactionType: $this->mapTransactionType($item->transaction_type),
            storeId: $storeId,
        );

        $breakdown = $this->rateCalculator->calculate(
            $context,
            $definition->calculation_strategy->value,
            $definition->enabled_modifiers,
            $definition->modifier_configs,
        );

        return [$rate->price, $breakdown->totalMinor()];
    }

    /**
     * Subtotal for manual/no-rate lines. Sale lines ignore chargeable days; hire-
     * based transaction types multiply by the same day count the editor displays.
     */
    private function manualLineSubtotal(
        int $unitPriceMinor,
        string $quantity,
        OpportunityItem $item,
        Opportunity $opportunity,
        string $currencyCode,
    ): int {
        $base = $this->quantityMinor->multiplyMinorByQuantity($unitPriceMinor, $quantity, $currencyCode);

        if ($item->transaction_type === LineItemTransactionType::Sale) {
            return $base;
        }

        $days = $this->chargeableDays->forItem($item, $opportunity);

        return $base * max(1, $days);
    }

    /**
     * Post-category, pre-deal line nets for the scoped items (used by deal-price
     * proportional distribution). Runs stages 1–2 without deal discounts.
     *
     * @param  Collection<int, OpportunityItem>  $scopedItems
     * @return array<int, int>
     */
    public function baselinePostCategoryNets(Opportunity $opportunity, Collection $scopedItems): array
    {
        $opportunity->loadMissing(['items.item', 'member']);
        $items = $opportunity->items;
        $currency = $this->currencyFor($opportunity);

        /** @var array<int, int> $baseSubtotals */
        $baseSubtotals = [];

        foreach ($items as $item) {
            if (! $item->item_type->isPriceable()) {
                continue;
            }

            $quantity = $this->quantityMinor->normalizeQuantity((string) $item->quantity);
            [$start, $end] = $this->effectiveDates($item, $opportunity);
            $product = $this->resolveProduct($item);

            [, $netSubtotal] = $this->resolveSubtotal(
                $item,
                $product,
                $currency,
                $quantity,
                $start,
                $end,
                $opportunity->store_id,
                null,
                $opportunity,
            );

            $baseSubtotals[(int) $item->id] = $netSubtotal;
        }

        $preDiscountByItem = $this->bundlePricingApplicator->apply(
            $opportunity,
            $items,
            $baseSubtotals,
            $currency,
        );

        $scopedIds = $scopedItems->pluck('id')->flip();
        $baselines = [];

        foreach ($items as $item) {
            if (! isset($scopedIds[$item->id])) {
                continue;
            }

            $preDiscount = $preDiscountByItem[(int) $item->id] ?? 0;
            $baselines[(int) $item->id] = $this->applyDiscount(
                $preDiscount,
                $this->effectiveDiscountPercent($item, $opportunity),
                $currency,
            );
        }

        return $baselines;
    }

    private function effectiveDiscountPercent(OpportunityItem $item, Opportunity $opportunity): ?string
    {
        /** @var list<string> $candidates */
        $candidates = [];

        if ($item->discount_percent !== null) {
            $candidates[] = (string) $item->discount_percent;
        }

        $at = $item->starts_at ?? $opportunity->starts_at ?? Carbon::now();

        foreach (app(DiscountSourceRegistry::class)->all() as $source) {
            $percent = $source->discountPercentForItem($item, $opportunity, Carbon::parse($at));

            if ($percent !== null) {
                $candidates[] = $percent;
            }
        }

        $best = null;

        foreach ($candidates as $candidate) {
            if ($best === null || bccomp($candidate, $best, 2) > 0) {
                $best = $candidate;
            }
        }

        return app(OpportunityItemDiscountPercentFilterRegistry::class)->apply($item, $best);
    }

    /**
     * Apply a percentage discount to a net amount, rounding at the minor-unit
     * boundary using the tenant {@see settings('pricing.rounding_direction')} setting.
     * A null/zero discount returns the amount unchanged.
     */
    private function applyDiscount(int $netMinor, ?string $discountPercent, string $currencyCode): int
    {
        if ($discountPercent === null || bccomp($discountPercent, '0', 10) === 0) {
            return $netMinor;
        }

        $rawDiscount = bcdiv(bcmul((string) $netMinor, $discountPercent, 10), '100', 10);
        $discount = $this->roundingStrategy->roundMinorAmount($rawDiscount, $currencyCode);

        return $netMinor - $discount;
    }

    /**
     * Resolve the line's effective rental window, inheriting the opportunity's
     * dates when the item has none.
     *
     * NOTE: both the item and the opportunity dates are baked into the firing
     * event at fire-time (the action resolves item ?? opportunity ?? a single
     * captured now() and passes concrete dates into the ItemAdded / ItemDatesChanged
     * payload). This method therefore never needs a now() fallback — a dateless
     * rate-priced line already carries a concrete window on its projection row, so
     * replay reproduces identical totals. A missing date is therefore an invariant
     * violation, surfaced as a LogicException rather than silently substituting a
     * non-deterministic now() (which would make the total irreproducible on replay).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function effectiveDates(OpportunityItem $item, Opportunity $opportunity): array
    {
        $start = $item->starts_at ?? $opportunity->starts_at;
        $end = $item->ends_at ?? $opportunity->ends_at;

        if ($start === null || $end === null) {
            throw new \LogicException(sprintf(
                'Opportunity item %s has no resolvable hire window: dates are baked into the firing event '.
                'at fire-time, so a dateless projection row is an invariant violation that would make the '.
                'total non-deterministic on replay.',
                $item->getKey() ?? '(unsaved)',
            ));
        }

        return [Carbon::parse($start), Carbon::parse($end)];
    }

    private function resolveProduct(OpportunityItem $item): ?Product
    {
        if (! $item->isProductBacked()) {
            return null;
        }

        return Product::query()->find($item->itemable_id);
    }

    private function mapTransactionType(LineItemTransactionType $type): RateTransactionType
    {
        return match ($type) {
            LineItemTransactionType::Sale => RateTransactionType::Sale,
            LineItemTransactionType::Service => RateTransactionType::Service,
            default => RateTransactionType::Rental, // Rental + SubRental
        };
    }
}
