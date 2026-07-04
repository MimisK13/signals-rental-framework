<?php

use App\Contracts\Tax\TaxResolver;
use App\Data\Tax\DocumentTaxLineInput;
use App\Data\Tax\DocumentTaxRequest;
use App\Enums\PricingRoundingDirection;
use App\Models\Currency;
use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Services\Tax\DocumentTaxBreakdown;
use App\Services\Tax\ZoneAwareTaxResolver;
use App\Services\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
});

function taxBreakdown(): DocumentTaxBreakdown
{
    return app(DocumentTaxBreakdown::class);
}

/**
 * Legacy grouped tax total used by OpportunityTotalsCalculator before P2.
 *
 * @param  array<int, int>  $netByProductTaxClass
 */
function legacyGroupedTaxTotal(array $netByProductTaxClass, string $currency, ?int $orgTaxClassId): int
{
    $calculator = app(TaxCalculator::class);
    $taxTotal = 0;

    foreach ($netByProductTaxClass as $productTaxClassId => $groupNet) {
        $taxTotal += $calculator->calculate(
            $groupNet,
            $currency,
            $orgTaxClassId,
            $productTaxClassId === 0 ? null : $productTaxClassId,
        )->taxAmount;
    }

    return $taxTotal;
}

/**
 * @return array{0: OrganisationTaxClass, 1: ProductTaxClass, 2: TaxRate}
 */
function wireStandardTaxRule(?OrganisationTaxClass $orgClass = null, ?ProductTaxClass $productClass = null, string $rate = '20.0000'): array
{
    $orgClass ??= OrganisationTaxClass::factory()->create();
    $productClass ??= ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['name' => 'Standard', 'rate' => $rate, 'is_active' => true]);
    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    return [$orgClass, $productClass, $taxRate];
}

describe('grouped tax totals (#238 regression)', function () {
    it('matches legacy grouped tax for a single-class basket', function () {
        [$orgClass, $productClass] = wireStandardTaxRule();

        $lines = [
            new DocumentTaxLineInput(lineKey: 'a', netMinor: 10_000, productTaxClassId: $productClass->id),
            new DocumentTaxLineInput(lineKey: 'b', netMinor: 5_000, productTaxClassId: $productClass->id),
        ];

        $summary = taxBreakdown()->summarize(new DocumentTaxRequest(
            lines: $lines,
            currencyCode: 'GBP',
            organisationTaxClassId: $orgClass->id,
        ));

        $legacy = legacyGroupedTaxTotal([$productClass->id => 15_000], 'GBP', $orgClass->id);

        expect($summary->totalTax)->toBe($legacy)
            ->and($summary->totalNet)->toBe(15_000)
            ->and($summary->totalGross())->toBe(15_000 + $legacy);
    });

    it('matches legacy grouped tax for a mixed-class basket with per-class rounding', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass1 = ProductTaxClass::factory()->create();
        $productClass2 = ProductTaxClass::factory()->create();

        $rate1 = TaxRate::factory()->create(['name' => 'Standard', 'rate' => '20.0000', 'is_active' => true]);
        $rate2 = TaxRate::factory()->create(['name' => 'Reduced', 'rate' => '5.0000', 'is_active' => true]);

        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass1->id,
            'tax_rate_id' => $rate1->id,
            'is_active' => true,
        ]);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass2->id,
            'tax_rate_id' => $rate2->id,
            'is_active' => true,
        ]);

        $net1 = 12_347;
        $net2 = 6_789;

        $summary = taxBreakdown()->summarize(new DocumentTaxRequest(
            lines: [
                new DocumentTaxLineInput(lineKey: 'line-1', netMinor: $net1, productTaxClassId: $productClass1->id),
                new DocumentTaxLineInput(lineKey: 'line-2', netMinor: $net2, productTaxClassId: $productClass2->id),
            ],
            currencyCode: 'GBP',
            organisationTaxClassId: $orgClass->id,
        ));

        $legacy = legacyGroupedTaxTotal([
            $productClass1->id => $net1,
            $productClass2->id => $net2,
        ], 'GBP', $orgClass->id);

        expect($summary->totalTax)->toBe($legacy)
            ->and($legacy)->toBe(2_469 + 339);
    });

    it('matches legacy grouped tax when lines have no product class (default class fallback)', function () {
        [$orgClass, $productClass] = wireStandardTaxRule();

        $summary = taxBreakdown()->summarize(new DocumentTaxRequest(
            lines: [
                new DocumentTaxLineInput(lineKey: 'no-class', netMinor: 5_000, productTaxClassId: null),
                new DocumentTaxLineInput(lineKey: 'taxed', netMinor: 1_000, productTaxClassId: $productClass->id),
            ],
            currencyCode: 'GBP',
            organisationTaxClassId: $orgClass->id,
        ));

        $legacy = legacyGroupedTaxTotal([
            0 => 5_000,
            $productClass->id => 1_000,
        ], 'GBP', $orgClass->id);

        // Batch B: null product class resolves to the default product tax class via TaxCalculator,
        // so the classless £50 line is taxed at 20% alongside the classified £10 line.
        expect($summary->totalTax)->toBe($legacy)
            ->and($summary->totalTax)->toBe(1_200);
    });

    it('extracts inclusive line amounts before taxing when pricesIncludeTax is true', function () {
        [$orgClass, $productClass] = wireStandardTaxRule();

        $summary = taxBreakdown()->summarize(new DocumentTaxRequest(
            lines: [
                new DocumentTaxLineInput(lineKey: 'gross', netMinor: 12_000, productTaxClassId: $productClass->id),
            ],
            currencyCode: 'GBP',
            organisationTaxClassId: $orgClass->id,
            pricesIncludeTax: true,
        ));

        expect($summary->totalNet)->toBe(10_000)
            ->and($summary->totalTax)->toBe(2_000)
            ->and($summary->lineAllocationsByKey()['gross']->taxMinor)->toBe(2_000);
    });
});

describe('largest-remainder line reconciliation', function () {
    it('allocates group tax so line totals sum exactly to the group tax', function () {
        [$orgClass, $productClass] = wireStandardTaxRule();

        $summary = taxBreakdown()->summarize(new DocumentTaxRequest(
            lines: [
                new DocumentTaxLineInput(lineKey: 'one', netMinor: 3, productTaxClassId: $productClass->id),
                new DocumentTaxLineInput(lineKey: 'two', netMinor: 3, productTaxClassId: $productClass->id),
                new DocumentTaxLineInput(lineKey: 'three', netMinor: 4, productTaxClassId: $productClass->id),
            ],
            currencyCode: 'GBP',
            organisationTaxClassId: $orgClass->id,
        ));

        $groupTax = $summary->groups[0]->taxTotal;
        $lineTaxSum = array_sum(array_map(fn ($line) => $line->taxMinor, $summary->lineAllocations));

        expect($groupTax)->toBe(2)
            ->and($lineTaxSum)->toBe($groupTax);
    });

    it('handles adversarial three-way splits where the group tax is not divisible by three', function () {
        [$orgClass, $productClass] = wireStandardTaxRule();

        $summary = taxBreakdown()->summarize(new DocumentTaxRequest(
            lines: [
                new DocumentTaxLineInput(lineKey: 'a', netMinor: 1, productTaxClassId: $productClass->id),
                new DocumentTaxLineInput(lineKey: 'b', netMinor: 1, productTaxClassId: $productClass->id),
                new DocumentTaxLineInput(lineKey: 'c', netMinor: 1, productTaxClassId: $productClass->id),
            ],
            currencyCode: 'GBP',
            organisationTaxClassId: $orgClass->id,
        ));

        $groupTax = $summary->groups[0]->taxTotal;
        $allocations = array_map(fn ($line) => $line->taxMinor, $summary->lineAllocationsByKey());

        expect($groupTax)->toBe(1)
            ->and(array_sum($allocations))->toBe(1)
            ->and(max($allocations))->toBe(1)
            ->and(min($allocations))->toBe(0);
    });

    it('reconciles each group independently in a mixed-class basket', function () {
        $orgClass = OrganisationTaxClass::factory()->create();
        $productClass1 = ProductTaxClass::factory()->create();
        $productClass2 = ProductTaxClass::factory()->create();

        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass1->id,
            'tax_rate_id' => TaxRate::factory()->create(['rate' => '20.0000', 'is_active' => true])->id,
            'is_active' => true,
        ]);
        TaxRule::factory()->create([
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $productClass2->id,
            'tax_rate_id' => TaxRate::factory()->create(['rate' => '5.0000', 'is_active' => true])->id,
            'is_active' => true,
        ]);

        $summary = taxBreakdown()->summarize(new DocumentTaxRequest(
            lines: [
                new DocumentTaxLineInput(lineKey: 'std-a', netMinor: 4_111, productTaxClassId: $productClass1->id),
                new DocumentTaxLineInput(lineKey: 'std-b', netMinor: 4_112, productTaxClassId: $productClass1->id),
                new DocumentTaxLineInput(lineKey: 'red-a', netMinor: 2_222, productTaxClassId: $productClass2->id),
                new DocumentTaxLineInput(lineKey: 'red-b', netMinor: 2_223, productTaxClassId: $productClass2->id),
            ],
            currencyCode: 'GBP',
            organisationTaxClassId: $orgClass->id,
        ));

        foreach ($summary->groups as $group) {
            $groupLineTax = 0;

            foreach ($summary->lineAllocations as $allocation) {
                if ($allocation->productTaxClassKey === $group->productTaxClassKey) {
                    $groupLineTax += $allocation->taxMinor;
                }
            }

            expect($groupLineTax)->toBe($group->taxTotal);
        }
    });
});

describe('currency scales', function () {
    it('rounds grouped tax at JPY 0dp boundaries', function () {
        Currency::factory()->create(['code' => 'JPY', 'decimal_places' => 0, 'symbol' => '¥']);
        [$orgClass, $productClass] = wireStandardTaxRule();

        $summary = taxBreakdown()->summarize(new DocumentTaxRequest(
            lines: [
                new DocumentTaxLineInput(lineKey: 'jpy', netMinor: 1_005, productTaxClassId: $productClass->id),
            ],
            currencyCode: 'JPY',
            organisationTaxClassId: $orgClass->id,
        ));

        $legacy = legacyGroupedTaxTotal([$productClass->id => 1_005], 'JPY', $orgClass->id);

        expect($summary->totalTax)->toBe($legacy)
            ->and($summary->totalTax)->toBe(201);
    });

    it('rounds grouped tax at KWD 3dp boundaries', function () {
        Currency::factory()->create(['code' => 'KWD', 'decimal_places' => 3, 'symbol' => 'KD']);
        [$orgClass, $productClass] = wireStandardTaxRule();

        $summary = taxBreakdown()->summarize(new DocumentTaxRequest(
            lines: [
                new DocumentTaxLineInput(lineKey: 'kwd', netMinor: 1_2345, productTaxClassId: $productClass->id),
            ],
            currencyCode: 'KWD',
            organisationTaxClassId: $orgClass->id,
        ));

        $legacy = legacyGroupedTaxTotal([$productClass->id => 1_2345], 'KWD', $orgClass->id);

        expect($summary->totalTax)->toBe($legacy);
    });
});

describe('TaxResolver seam', function () {
    it('binds the zone-aware resolver as the default TaxResolver implementation', function () {
        expect(app(TaxResolver::class))->toBeInstanceOf(ZoneAwareTaxResolver::class);
    });
});
