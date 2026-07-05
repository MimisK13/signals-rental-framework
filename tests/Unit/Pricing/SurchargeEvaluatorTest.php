<?php

use App\Enums\LineItemTransactionType;
use App\Enums\PricingRoundingDirection;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunitySurchargeGroup;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Surcharge;
use App\Models\SurchargeGroup;
use App\Services\Pricing\SurchargeEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    $this->evaluator = app(SurchargeEvaluator::class);
});

it('calculates a percent surcharge on the post-discount line net', function () {
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->percent('10.00')->create();

    $opportunity = Opportunity::factory()->create();
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Sale,
    ]);

    $results = $this->evaluator->evaluateForItem(
        $item,
        $opportunity,
        collect([$surcharge]),
        postDiscountNetMinor: 9000,
        currencyCode: 'GBP',
        at: now('UTC'),
        fallbackProductTaxClassId: null,
    );

    expect($results)->toHaveCount(1)
        ->and($results[0]->amount_minor)->toBe(900);
});

it('calculates a fixed per-unit surcharge multiplied by quantity', function () {
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->fixed(250)->create();

    $opportunity = Opportunity::factory()->create();
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'quantity' => '3',
        'transaction_type' => LineItemTransactionType::Sale,
    ]);

    $results = $this->evaluator->evaluateForItem(
        $item,
        $opportunity,
        collect([$surcharge]),
        postDiscountNetMinor: 5000,
        currencyCode: 'GBP',
        at: now('UTC'),
        fallbackProductTaxClassId: null,
    );

    expect($results[0]->amount_minor)->toBe(750);
});

it('skips non-default surcharges with empty criteria', function () {
    $group = SurchargeGroup::factory()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->nonDefault()->percent('5.00')->create([
        'criteria' => [],
    ]);

    $opportunity = Opportunity::factory()->create();
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'transaction_type' => LineItemTransactionType::Sale,
    ]);

    $results = $this->evaluator->evaluateForItem(
        $item,
        $opportunity,
        collect([$surcharge]),
        postDiscountNetMinor: 10_000,
        currencyCode: 'GBP',
        at: now('UTC'),
        fallbackProductTaxClassId: null,
    );

    expect($results)->toBeEmpty();
});

it('respects temporal validity windows', function () {
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->percent('5.00')->create([
        'valid_from' => '2026-08-01T00:00:00Z',
    ]);

    $opportunity = Opportunity::factory()->create([
        'starts_at' => '2026-07-01T09:00:00Z',
    ]);
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'starts_at' => '2026-07-01T09:00:00Z',
        'transaction_type' => LineItemTransactionType::Sale,
    ]);

    $results = $this->evaluator->evaluateForItem(
        $item,
        $opportunity,
        collect([$surcharge]),
        postDiscountNetMinor: 10_000,
        currencyCode: 'GBP',
        at: $this->evaluator->evaluationInstant($item, $opportunity),
        fallbackProductTaxClassId: null,
    );

    expect($results)->toBeEmpty();
});

it('resolves active groups from default groups when no pivot exists', function () {
    $default = SurchargeGroup::factory()->defaultGroup()->create();
    $optional = SurchargeGroup::factory()->create(['is_default' => false]);
    $opportunity = Opportunity::factory()->create();

    expect($this->evaluator->activeGroupIds($opportunity))->toBe([$default->id]);
});

it('uses configured pivot state over default group behaviour', function () {
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $opportunity = Opportunity::factory()->create();

    OpportunitySurchargeGroup::factory()->create([
        'opportunity_id' => $opportunity->id,
        'surcharge_group_id' => $group->id,
        'is_active' => false,
    ]);

    expect($this->evaluator->activeGroupIds($opportunity))->toBe([]);
});

it('matches criteria via CriteriaMatcher predicates', function () {
    $product = Product::factory()->rental()->create();
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->percent('5.00')->create([
        'criteria' => ['product_ids' => [$product->id]],
        'is_default' => false,
    ]);

    $opportunity = Opportunity::factory()->create();
    $matching = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Rental,
    ]);
    $other = OpportunityItem::factory()->for($opportunity)->create([
        'transaction_type' => LineItemTransactionType::Rental,
    ]);

    $collection = collect([$surcharge]);

    $matched = $this->evaluator->evaluateForItem($matching, $opportunity, $collection, 10_000, 'GBP', now('UTC'), null);
    $skipped = $this->evaluator->evaluateForItem($other, $opportunity, $collection, 10_000, 'GBP', now('UTC'), null);

    expect($matched)->toHaveCount(1)
        ->and($skipped)->toBeEmpty();
});

it('uses the surcharge own tax class when configured', function () {
    $taxClass = ProductTaxClass::factory()->create();
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->percent('5.00')->create([
        'product_tax_class_id' => $taxClass->id,
    ]);

    expect($this->evaluator->resolveTaxClassId($surcharge, 99))->toBe($taxClass->id);
});

it('falls back to product tax class when surcharge class is null', function () {
    $group = SurchargeGroup::factory()->defaultGroup()->create();
    $surcharge = Surcharge::factory()->for($group, 'surchargeGroup')->percent('5.00')->create([
        'product_tax_class_id' => null,
    ]);

    expect($this->evaluator->resolveTaxClassId($surcharge, 42))->toBe(42);
});
