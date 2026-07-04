<?php

use App\Data\Tax\TaxZoneRuleMatchRequest;
use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use App\Models\TaxRule;
use App\Models\TaxZone;
use App\Services\Tax\TaxZoneRuleResolver;

beforeEach(function () {
    $this->resolver = app(TaxZoneRuleResolver::class);
    $this->uk = TaxZone::factory()->uk()->create();
    $this->euMember = TaxZone::factory()->euMember()->create();
    $this->world = TaxZone::factory()->world()->create();
});

dataset('zone rule result types', [
    'standard UK domestic B2C' => [
        'seller' => 'uk',
        'buyer' => 'uk',
        'buyerType' => TaxBuyerType::Individual,
        'hasTaxId' => false,
        'ruleBuyerType' => null,
        'ruleHasTaxId' => null,
        'resultType' => TaxResultType::Standard,
        'priority' => 100,
    ],
    'reverse charge EU B2B with tax id' => [
        'seller' => 'uk',
        'buyer' => 'euMember',
        'buyerType' => TaxBuyerType::Business,
        'hasTaxId' => true,
        'ruleBuyerType' => TaxBuyerType::Business,
        'ruleHasTaxId' => true,
        'resultType' => TaxResultType::ReverseCharge,
        'priority' => 90,
    ],
    'zero rated export to world' => [
        'seller' => 'uk',
        'buyer' => 'world',
        'buyerType' => TaxBuyerType::Business,
        'hasTaxId' => false,
        'ruleBuyerType' => null,
        'ruleHasTaxId' => null,
        'resultType' => TaxResultType::ZeroRated,
        'priority' => 80,
    ],
    'exempt domestic supply' => [
        'seller' => 'uk',
        'buyer' => 'uk',
        'buyerType' => TaxBuyerType::Business,
        'hasTaxId' => true,
        'ruleBuyerType' => TaxBuyerType::Business,
        'ruleHasTaxId' => true,
        'resultType' => TaxResultType::Exempt,
        'priority' => 70,
    ],
    'outside scope EU other' => [
        'seller' => 'uk',
        'buyer' => 'euMember',
        'buyerType' => TaxBuyerType::Individual,
        'hasTaxId' => false,
        'ruleBuyerType' => TaxBuyerType::Individual,
        'ruleHasTaxId' => null,
        'resultType' => TaxResultType::OutsideScope,
        'priority' => 60,
    ],
]);

it('resolves the matching zone rule for each result type', function (
    string $seller,
    string $buyer,
    TaxBuyerType $buyerType,
    bool $hasTaxId,
    ?TaxBuyerType $ruleBuyerType,
    ?bool $ruleHasTaxId,
    TaxResultType $resultType,
    int $priority,
) {
    $sellerZone = $this->{$seller};
    $buyerZone = $this->{$buyer};

    TaxRule::factory()->zoneMatch([
        'seller_zone_id' => $sellerZone->id,
        'buyer_zone_id' => $buyerZone->id,
        'buyer_type' => $ruleBuyerType,
        'has_tax_id' => $ruleHasTaxId,
        'result_type' => $resultType,
        'priority' => $priority,
        'name' => "Rule {$resultType->value}",
    ])->create();

    $rule = $this->resolver->resolve(new TaxZoneRuleMatchRequest(
        seller_zone_id: $sellerZone->id,
        buyer_zone_id: $buyerZone->id,
        buyer_type: $buyerType,
        has_tax_id: $hasTaxId,
    ));

    expect($rule)->not->toBeNull()
        ->and($rule->result_type)->toBe($resultType);
})->with('zone rule result types');

it('prefers the highest priority matching rule', function () {
    TaxRule::factory()->zoneMatch([
        'seller_zone_id' => $this->uk->id,
        'buyer_zone_id' => $this->euMember->id,
        'buyer_type' => TaxBuyerType::Business,
        'has_tax_id' => true,
        'result_type' => TaxResultType::Standard,
        'priority' => 10,
        'name' => 'Low priority',
    ])->create();

    TaxRule::factory()->zoneMatch([
        'seller_zone_id' => $this->uk->id,
        'buyer_zone_id' => $this->euMember->id,
        'buyer_type' => TaxBuyerType::Business,
        'has_tax_id' => true,
        'result_type' => TaxResultType::ReverseCharge,
        'priority' => 50,
        'name' => 'High priority',
    ])->create();

    $rule = $this->resolver->resolveFromContext(
        $this->uk->id,
        $this->euMember->id,
        TaxBuyerType::Business,
        hasTaxId: true,
    );

    expect($rule?->result_type)->toBe(TaxResultType::ReverseCharge)
        ->and($rule?->name)->toBe('High priority');
});

it('treats null buyer_type on rule as matching any buyer type', function () {
    TaxRule::factory()->zoneMatch([
        'seller_zone_id' => $this->uk->id,
        'buyer_zone_id' => $this->world->id,
        'buyer_type' => null,
        'has_tax_id' => null,
        'result_type' => TaxResultType::ZeroRated,
        'priority' => 20,
    ])->create();

    $b2b = $this->resolver->resolveFromContext($this->uk->id, $this->world->id, TaxBuyerType::Business);
    $b2c = $this->resolver->resolveFromContext($this->uk->id, $this->world->id, TaxBuyerType::Individual);

    expect($b2b?->result_type)->toBe(TaxResultType::ZeroRated)
        ->and($b2c?->result_type)->toBe(TaxResultType::ZeroRated);
});

it('returns null when no active zone rule matches', function () {
    TaxRule::factory()->zoneMatch([
        'seller_zone_id' => $this->uk->id,
        'buyer_zone_id' => $this->euMember->id,
        'buyer_type' => TaxBuyerType::Business,
        'has_tax_id' => true,
        'result_type' => TaxResultType::ReverseCharge,
        'is_active' => false,
    ])->create();

    $rule = $this->resolver->resolveFromContext(
        $this->uk->id,
        $this->euMember->id,
        TaxBuyerType::Business,
        hasTaxId: true,
    );

    expect($rule)->toBeNull();
});

it('does not match class-map rules without zone columns', function () {
    TaxRule::factory()->create([
        'seller_zone_id' => null,
        'buyer_zone_id' => null,
        'result_type' => null,
    ]);

    $rule = $this->resolver->resolveFromContext(
        $this->uk->id,
        $this->uk->id,
        TaxBuyerType::Individual,
    );

    expect($rule)->toBeNull();
});
