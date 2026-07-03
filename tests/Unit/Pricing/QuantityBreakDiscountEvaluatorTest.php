<?php

use App\Enums\DiscountRuleScope;
use App\Enums\LineItemTransactionType;
use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Services\Pricing\DiscountRuleEvaluator;
use App\Services\Pricing\QuantityContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function quantityLineItem(
    Opportunity $opportunity,
    Product $product,
    int $quantity,
    ?int $revenueGroupId = null,
): OpportunityItem {
    return OpportunityItem::factory()->for($opportunity)->create([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Rental,
        'quantity' => (string) $quantity,
        'revenue_group_id' => $revenueGroupId,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-02T17:00:00Z'),
    ]);
}

it('fires a quantity break at the minimum threshold but not below', function () {
    $category = DiscountCategory::factory()->create();
    $rules = collect([
        DiscountRule::factory()->for($category)->create([
            'discount_percent' => '15.00',
            'criteria' => ['quantity_min' => 10],
            'rule_scope' => DiscountRuleScope::PerLine,
        ]),
    ]);

    $product = Product::factory()->rental()->create();
    $opportunity = Opportunity::factory()->create();
    $below = quantityLineItem($opportunity, $product, 9);
    $atThreshold = quantityLineItem($opportunity, $product, 10);

    $evaluator = app(DiscountRuleEvaluator::class);
    $maps = app(QuantityContextResolver::class)->resolve($opportunity, collect([$below, $atThreshold]));
    $at = Carbon::parse('2026-07-01T09:00:00Z');

    expect($evaluator->evaluateForItem($below->load('item'), $opportunity, $rules, $at, $maps))->toBeNull()
        ->and($evaluator->evaluateForItem($atThreshold->load('item'), $opportunity, $rules, $at, $maps))->toBe('15.00');
});

it('respects quantity_max upper bounds', function () {
    $category = DiscountCategory::factory()->create();
    $rules = collect([
        DiscountRule::factory()->for($category)->create([
            'discount_percent' => '12.00',
            'criteria' => ['quantity_min' => 5, 'quantity_max' => 10],
            'rule_scope' => DiscountRuleScope::PerLine,
        ]),
    ]);

    $product = Product::factory()->rental()->create();
    $opportunity = Opportunity::factory()->create();
    $within = quantityLineItem($opportunity, $product, 10);
    $above = quantityLineItem($opportunity, $product, 11);

    $evaluator = app(DiscountRuleEvaluator::class);
    $maps = app(QuantityContextResolver::class)->resolve($opportunity, collect([$within, $above]));
    $at = Carbon::parse('2026-07-01T09:00:00Z');

    expect($evaluator->evaluateForItem($within->load('item'), $opportunity, $rules, $at, $maps))->toBe('12.00')
        ->and($evaluator->evaluateForItem($above->load('item'), $opportunity, $rules, $at, $maps))->toBeNull();
});

it('uses aggregate_product quantity summed across opportunity lines', function () {
    $category = DiscountCategory::factory()->create();
    $rules = collect([
        DiscountRule::factory()->for($category)->create([
            'discount_percent' => '20.00',
            'criteria' => ['quantity_min' => 10],
            'rule_scope' => DiscountRuleScope::AggregateProduct,
        ]),
    ]);

    $product = Product::factory()->rental()->create();
    $opportunity = Opportunity::factory()->create();
    $lineA = quantityLineItem($opportunity, $product, 4);
    $lineB = quantityLineItem($opportunity, $product, 6);

    $evaluator = app(DiscountRuleEvaluator::class);
    $maps = app(QuantityContextResolver::class)->resolve($opportunity, collect([$lineA, $lineB]));
    $at = Carbon::parse('2026-07-01T09:00:00Z');

    expect($evaluator->evaluateForItem($lineA->load('item'), $opportunity, $rules, $at, $maps))->toBe('20.00')
        ->and($evaluator->evaluateForItem($lineB->load('item'), $opportunity, $rules, $at, $maps))->toBe('20.00');
});

it('does not apply aggregate_product thresholds to per_line scoped rules', function () {
    $category = DiscountCategory::factory()->create();
    $rules = collect([
        DiscountRule::factory()->for($category)->create([
            'discount_percent' => '20.00',
            'criteria' => ['quantity_min' => 10],
            'rule_scope' => DiscountRuleScope::PerLine,
        ]),
    ]);

    $product = Product::factory()->rental()->create();
    $opportunity = Opportunity::factory()->create();
    $lineA = quantityLineItem($opportunity, $product, 4);
    $lineB = quantityLineItem($opportunity, $product, 6);

    $evaluator = app(DiscountRuleEvaluator::class);
    $maps = app(QuantityContextResolver::class)->resolve($opportunity, collect([$lineA, $lineB]));
    $at = Carbon::parse('2026-07-01T09:00:00Z');

    expect($evaluator->evaluateForItem($lineA->load('item'), $opportunity, $rules, $at, $maps))->toBeNull()
        ->and($evaluator->evaluateForItem($lineB->load('item'), $opportunity, $rules, $at, $maps))->toBeNull();
});

it('uses aggregate_group quantity within a revenue group', function () {
    $category = DiscountCategory::factory()->create();
    $rules = collect([
        DiscountRule::factory()->for($category)->create([
            'discount_percent' => '18.00',
            'criteria' => ['quantity_min' => 8],
            'rule_scope' => DiscountRuleScope::AggregateGroup,
        ]),
    ]);

    $productA = Product::factory()->rental()->create();
    $productB = Product::factory()->rental()->create();
    $opportunity = Opportunity::factory()->create();
    $groupId = 42;

    $lineA = quantityLineItem($opportunity, $productA, 3, $groupId);
    $lineB = quantityLineItem($opportunity, $productB, 5, $groupId);
    $otherGroup = quantityLineItem($opportunity, $productA, 5, 99);

    $evaluator = app(DiscountRuleEvaluator::class);
    $maps = app(QuantityContextResolver::class)->resolve($opportunity, collect([$lineA, $lineB, $otherGroup]));
    $at = Carbon::parse('2026-07-01T09:00:00Z');

    expect($evaluator->evaluateForItem($lineA->load('item'), $opportunity, $rules, $at, $maps))->toBe('18.00')
        ->and($evaluator->evaluateForItem($lineB->load('item'), $opportunity, $rules, $at, $maps))->toBe('18.00')
        ->and($evaluator->evaluateForItem($otherGroup->load('item'), $opportunity, $rules, $at, $maps))->toBeNull();
});
