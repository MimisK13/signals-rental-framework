<?php

use App\Enums\TaxBuyerType;
use App\Models\Member;
use App\Models\Store;
use App\Models\TaxZone;

it('persists member tax context columns', function () {
    $zone = TaxZone::factory()->uk()->create();

    $member = Member::factory()->organisation()->create([
        'tax_zone_id' => $zone->id,
        'buyer_type' => TaxBuyerType::Individual->value,
        'tax_exempt' => true,
    ]);

    $member->refresh();

    expect($member->tax_zone_id)->toBe($zone->id)
        ->and($member->buyer_type)->toBe(TaxBuyerType::Individual)
        ->and($member->tax_exempt)->toBeTrue();
});

it('defaults member buyer_type to business and tax_exempt to false', function () {
    $member = Member::factory()->organisation()->create([
        'tax_zone_id' => null,
    ]);

    $member->refresh();

    expect($member->buyer_type)->toBe(TaxBuyerType::Business)
        ->and($member->tax_exempt)->toBeFalse()
        ->and($member->tax_zone_id)->toBeNull();
});

it('persists store seller tax_zone_id', function () {
    $zone = TaxZone::factory()->uk()->create();

    $store = Store::factory()->create([
        'tax_zone_id' => $zone->id,
    ]);

    expect($store->fresh()->tax_zone_id)->toBe($zone->id);
});

it('resolves member and store tax zone relationships', function () {
    $zone = TaxZone::factory()->euMember()->create();

    $member = Member::factory()->organisation()->create(['tax_zone_id' => $zone->id]);
    $store = Store::factory()->create(['tax_zone_id' => $zone->id]);

    expect($member->taxZone?->slug)->toBe('eu_member')
        ->and($store->taxZone?->slug)->toBe('eu_member');
});
