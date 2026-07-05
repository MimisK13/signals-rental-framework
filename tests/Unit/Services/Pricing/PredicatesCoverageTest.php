<?php

use App\Enums\DiscountRuleScope;
use App\Enums\RateTransactionType;
use App\Services\Pricing\Predicates\ProductGroupIdsPredicate;
use App\Services\Pricing\Predicates\ProductIdsPredicate;
use App\Services\Pricing\Predicates\QuantityMaxPredicate;
use App\Services\Pricing\Predicates\QuantityMinPredicate;
use App\Services\Pricing\Predicates\TagNamesPredicate;
use App\Services\Pricing\Predicates\TransactionTypesPredicate;
use App\ValueObjects\Pricing\DiscountCriteriaContext;

it('product group ids predicate rejects lines without a product group', function () {
    $predicate = new ProductGroupIdsPredicate;
    $context = new DiscountCriteriaContext(product_group_id: null, product_id: 1);

    expect($predicate->matches([10, 20], $context))->toBeFalse();
});

it('product ids predicate rejects lines without a product id', function () {
    $predicate = new ProductIdsPredicate;
    $context = new DiscountCriteriaContext(product_id: null);

    expect($predicate->matches([10, 20], $context))->toBeFalse();
});

it('quantity max predicate rejects lines with unknown quantity context', function () {
    $predicate = new QuantityMaxPredicate;
    $context = new DiscountCriteriaContext(
        line_quantity: null,
        rule_scope: DiscountRuleScope::PerLine,
    );

    expect($predicate->matches(5, $context))->toBeFalse();
});

it('quantity min predicate rejects lines with unknown quantity context', function () {
    $predicate = new QuantityMinPredicate;
    $context = new DiscountCriteriaContext(
        line_quantity: null,
        rule_scope: DiscountRuleScope::PerLine,
    );

    expect($predicate->matches(5, $context))->toBeFalse();
});

it('quantity max predicate uses aggregate product quantity when scoped', function () {
    $predicate = new QuantityMaxPredicate;
    $context = new DiscountCriteriaContext(
        line_quantity: 1,
        aggregate_product_quantity: 8,
        rule_scope: DiscountRuleScope::AggregateProduct,
    );

    expect($predicate->matches(10, $context))->toBeTrue()
        ->and($predicate->matches(5, $context))->toBeFalse();
});

it('tag names predicate accepts empty required tags after normalisation', function () {
    $predicate = new TagNamesPredicate;

    expect($predicate->matches(['', 0, false], new DiscountCriteriaContext(tag_names: ['vip'])))->toBeTrue();
});

it('tag names predicate requires every configured tag to be present', function () {
    $predicate = new TagNamesPredicate;
    $context = new DiscountCriteriaContext(tag_names: ['VIP']);

    expect($predicate->matches(['vip', 'rush'], $context))->toBeFalse()
        ->and($predicate->matches(['vip', 'rush'], new DiscountCriteriaContext(tag_names: ['vip', 'rush'])))->toBeTrue();
});

it('transaction types predicate rejects uncoercible context transaction types', function () {
    $predicate = new TransactionTypesPredicate;
    $context = new DiscountCriteriaContext(transaction_type: 'not-a-real-type');

    expect($predicate->matches(['rental'], $context))->toBeFalse();
});

it('transaction types predicate matches coerced transaction types', function () {
    $predicate = new TransactionTypesPredicate;
    $context = new DiscountCriteriaContext(transaction_type: RateTransactionType::Rental->value);

    expect($predicate->matches(['rental', 'sale'], $context))->toBeTrue()
        ->and($predicate->matches(['sale'], $context))->toBeFalse();
});
