<?php

/**
 * Coverage burn-up batch 5 — unit tests for pure/service helpers.
 */

use App\Data\Tax\DocumentTaxLineInput;
use App\Enums\DiscountRuleScope;
use App\Services\Pricing\Predicates\QuantityMinPredicate;
use App\Services\Tax\DocumentTaxBreakdown;
use App\Support\BrandColour;
use App\ValueObjects\Pricing\DiscountCriteriaContext;
use Tests\TestCase;

uses(TestCase::class);

function burnup5UnitInvoke(object|string $object, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke(is_string($object) ? null : $object, ...$args);
}

it('QuantityMinPredicate matches accepts empty criteria and aggregate group quantity', function () {
    $predicate = new QuantityMinPredicate;

    expect($predicate->matches(null, new DiscountCriteriaContext(line_quantity: 1)))->toBeTrue()
        ->and($predicate->matches('', new DiscountCriteriaContext(line_quantity: 1)))->toBeTrue()
        ->and($predicate->matches(3, new DiscountCriteriaContext(
            line_quantity: 1,
            aggregate_group_quantity: 5,
            rule_scope: DiscountRuleScope::AggregateGroup,
        )))->toBeTrue()
        ->and($predicate->matches(10, new DiscountCriteriaContext(
            line_quantity: 1,
            aggregate_group_quantity: 5,
            rule_scope: DiscountRuleScope::AggregateGroup,
        )))->toBeFalse();
});

it('BrandColour rgb expands shorthand hex and falls back for invalid values', function () {
    expect(burnup5UnitInvoke(BrandColour::class, 'rgb', ['#abc']))
        ->toBe([170, 187, 204]);

    expect(burnup5UnitInvoke(BrandColour::class, 'rgb', ['not-a-colour']))
        ->toBe(burnup5UnitInvoke(BrandColour::class, 'rgb', [BrandColour::DEFAULT_PRIMARY]));
});

it('DocumentTaxBreakdown distributeLargestRemainder returns early when pennies divide evenly', function () {
    $members = [
        ['line' => new DocumentTaxLineInput(lineKey: 'a', netMinor: 100), 'netMinor' => 100],
        ['line' => new DocumentTaxLineInput(lineKey: 'b', netMinor: 100), 'netMinor' => 100],
    ];

    $allocations = burnup5UnitInvoke(app(DocumentTaxBreakdown::class), 'distributeLargestRemainder', [$members, 20]);

    expect($allocations)->toBe(['a' => 10, 'b' => 10]);
});
