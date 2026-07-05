<?php

use App\Enums\LineItemTransactionType;
use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Services\Pricing\DiscountRuleEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function evaluatorLineItem(Product $product, Opportunity $opportunity): OpportunityItem
{
    return OpportunityItem::factory()->for($opportunity)->create([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Rental,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-05T17:00:00Z'),
    ]);
}

it('returns the highest matching discount percent across rules', function () {
    $category = DiscountCategory::factory()->create();
    $rules = collect([
        DiscountRule::factory()->for($category)->create(['discount_percent' => '10.00', 'criteria' => []]),
        DiscountRule::factory()->for($category)->create(['discount_percent' => '15.00', 'criteria' => []]),
        DiscountRule::factory()->for($category)->create(['discount_percent' => '20.00', 'criteria' => []]),
    ]);

    $product = Product::factory()->rental()->create();
    $opportunity = Opportunity::factory()->create();
    $item = evaluatorLineItem($product, $opportunity);

    $result = app(DiscountRuleEvaluator::class)->evaluateForItem(
        $item->load('item'),
        $opportunity,
        $rules,
        Carbon::parse('2026-07-01T09:00:00Z'),
    );

    expect($result)->toBe('20.00');
});

it('skips rules outside the temporal window', function () {
    $category = DiscountCategory::factory()->create();
    $rules = collect([
        DiscountRule::factory()->for($category)->create([
            'discount_percent' => '25.00',
            'criteria' => [],
            'valid_from' => Carbon::parse('2026-08-01T00:00:00Z'),
        ]),
        DiscountRule::factory()->for($category)->create([
            'discount_percent' => '12.50',
            'criteria' => [],
            'valid_to' => Carbon::parse('2026-06-30T23:59:59Z'),
        ]),
    ]);

    $product = Product::factory()->rental()->create();
    $opportunity = Opportunity::factory()->create();
    $item = evaluatorLineItem($product, $opportunity);

    $result = app(DiscountRuleEvaluator::class)->evaluateForItem(
        $item->load('item'),
        $opportunity,
        $rules,
        Carbon::parse('2026-07-01T09:00:00Z'),
    );

    expect($result)->toBeNull();
});

it('skips non-discountable catalogue products', function () {
    $category = DiscountCategory::factory()->create();
    $rules = collect([
        DiscountRule::factory()->for($category)->blanket()->create(),
    ]);

    $product = Product::factory()->rental()->create(['discountable' => false]);
    $opportunity = Opportunity::factory()->create();
    $item = evaluatorLineItem($product, $opportunity);

    $result = app(DiscountRuleEvaluator::class)->evaluateForItem(
        $item->load('item'),
        $opportunity,
        $rules,
        Carbon::parse('2026-07-01T09:00:00Z'),
    );

    expect($result)->toBeNull();
});

it('matches product-specific criteria via CriteriaMatcher', function () {
    $category = DiscountCategory::factory()->create();
    $productA = Product::factory()->rental()->create();
    $productB = Product::factory()->rental()->create();

    $rules = collect([
        DiscountRule::factory()->for($category)->create([
            'discount_percent' => '18.00',
            'criteria' => ['product_ids' => [$productA->id]],
        ]),
    ]);

    $opportunity = Opportunity::factory()->create();
    $item = evaluatorLineItem($productA, $opportunity);
    $other = evaluatorLineItem($productB, $opportunity);

    $evaluator = app(DiscountRuleEvaluator::class);
    $at = Carbon::parse('2026-07-01T09:00:00Z');

    expect($evaluator->evaluateForItem($item->load('item'), $opportunity, $rules, $at))->toBe('18.00')
        ->and($evaluator->evaluateForItem($other->load('item'), $opportunity, $rules, $at))->toBeNull();
});
