<?php

use App\Models\DiscountCategory;
use App\Models\Member;
use App\Models\PriceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * SQLite-safe lane: members can reference real pricing categories and null ids
 * round-trip. FK enforcement and nullOnDelete are proven on Postgres in
 * tests/Pgsql/PricingCategoryForeignKeysPostgresTest.php.
 */
it('persists an organisation linked to price and discount categories', function () {
    $priceCategory = PriceCategory::factory()->create();
    $discountCategory = DiscountCategory::factory()->create();

    $member = Member::factory()->organisation()->create([
        'price_category_id' => $priceCategory->id,
        'discount_category_id' => $discountCategory->id,
    ]);

    expect($member->fresh()->price_category_id)->toBe($priceCategory->id);
    expect($member->fresh()->discount_category_id)->toBe($discountCategory->id);
});

it('allows null pricing category ids on members', function () {
    $member = Member::factory()->organisation()->create([
        'price_category_id' => null,
        'discount_category_id' => null,
    ]);

    expect($member->fresh()->price_category_id)->toBeNull();
    expect($member->fresh()->discount_category_id)->toBeNull();
});
