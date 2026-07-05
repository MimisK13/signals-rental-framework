<?php

use App\Services\Pricing\CriteriaMatcher;
use App\ValueObjects\Pricing\DiscountCriteriaContext;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->matcher = app(CriteriaMatcher::class);
});

it('matches all contexts when criteria is empty', function (?array $criteria) {
    $context = new DiscountCriteriaContext(
        product_group_id: 99,
        tag_names: ['lighting'],
        product_id: 42,
        transaction_type: 'rental',
    );

    expect($this->matcher->matches($criteria, $context))->toBeTrue();
})->with([
    'null' => [null],
    'empty array' => [[]],
    'empty keys' => [['product_group_ids' => [], 'tag_names' => [], 'product_ids' => [], 'transaction_types' => []]],
]);

describe('product_group_ids predicate', function () {
    it('matches when the context group is listed', function () {
        $context = new DiscountCriteriaContext(product_group_id: 5);

        expect($this->matcher->matches(['product_group_ids' => [5, 6]], $context))->toBeTrue();
    });

    it('does not match when the context group is absent', function () {
        $context = new DiscountCriteriaContext(product_group_id: 5);

        expect($this->matcher->matches(['product_group_ids' => [6]], $context))->toBeFalse();
    });

    it('does not match when the context group is null', function () {
        $context = new DiscountCriteriaContext(product_group_id: null);

        expect($this->matcher->matches(['product_group_ids' => [5]], $context))->toBeFalse();
    });
});

describe('product_ids predicate', function () {
    it('matches when the context product is listed', function () {
        $context = new DiscountCriteriaContext(product_id: 10);

        expect($this->matcher->matches(['product_ids' => [10]], $context))->toBeTrue();
    });

    it('does not match when the context product is absent', function () {
        $context = new DiscountCriteriaContext(product_id: 10);

        expect($this->matcher->matches(['product_ids' => [11]], $context))->toBeFalse();
    });
});

describe('transaction_types predicate', function () {
    it('matches allowed transaction types case-insensitively', function (string $contextType, array $criteriaTypes, bool $expected) {
        $context = new DiscountCriteriaContext(transaction_type: $contextType);

        expect($this->matcher->matches(['transaction_types' => $criteriaTypes], $context))->toBe($expected);
    })->with([
        'rental matches rental' => ['rental', ['rental'], true],
        'Rental matches rental' => ['Rental', ['rental'], true],
        'sale excluded' => ['rental', ['sale'], false],
        'service in list' => ['service', ['rental', 'service'], true],
        'missing context' => ['', ['rental'], false],
    ]);
});

describe('tag_names predicate', function () {
    it('requires every configured tag to be present (AND logic)', function (array $criteriaTags, array $contextTags, bool $expected) {
        $context = new DiscountCriteriaContext(tag_names: $contextTags);

        expect($this->matcher->matches(['tag_names' => $criteriaTags], $context))->toBe($expected);
    })->with([
        'single tag present' => [['lighting'], ['lighting'], true],
        'single tag missing' => [['lighting'], ['audio'], false],
        'all tags required' => [['lighting', 'led'], ['lighting', 'led', 'dmx'], true],
        'partial tag set fails' => [['lighting', 'led'], ['lighting'], false],
        'case insensitive' => [['Lighting'], ['lighting'], true],
        'duplicate context tags ok' => [['lighting'], ['lighting', 'lighting'], true],
    ]);
});

describe('combined criteria', function () {
    it('matches only when every active predicate passes', function (array $criteria, DiscountCriteriaContext $context, bool $expected) {
        expect($this->matcher->matches($criteria, $context))->toBe($expected);
    })->with([
        'group + product + type + tags' => [
            [
                'product_group_ids' => [1],
                'product_ids' => [10],
                'transaction_types' => ['rental'],
                'tag_names' => ['lighting', 'led'],
            ],
            new DiscountCriteriaContext(1, ['lighting', 'led'], 10, 'rental'),
            true,
        ],
        'fails when tags incomplete' => [
            [
                'product_group_ids' => [1],
                'product_ids' => [10],
                'transaction_types' => ['rental'],
                'tag_names' => ['lighting', 'led'],
            ],
            new DiscountCriteriaContext(1, ['lighting'], 10, 'rental'),
            false,
        ],
        'fails when product group wrong' => [
            [
                'product_group_ids' => [2],
                'product_ids' => [10],
                'transaction_types' => ['rental'],
            ],
            new DiscountCriteriaContext(1, [], 10, 'rental'),
            false,
        ],
        'blanket with empty active keys' => [
            ['product_group_ids' => [], 'tag_names' => ['lighting']],
            new DiscountCriteriaContext(tag_names: ['lighting']),
            true,
        ],
    ]);
});

it('ignores unknown criteria keys without failing', function () {
    $context = new DiscountCriteriaContext(product_id: 10);

    expect($this->matcher->matches([
        'product_ids' => [10],
        'future_plugin_dimension' => ['anything'],
    ], $context))->toBeTrue();
});
