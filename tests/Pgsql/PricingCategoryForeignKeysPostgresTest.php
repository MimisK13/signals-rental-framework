<?php

use App\Models\DiscountCategory;
use App\Models\Member;
use App\Models\PriceCategory;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL members pricing-category foreign-key lane (P5)
|--------------------------------------------------------------------------
|
| members.price_category_id and members.discount_category_id are FK-constrained
| with nullOnDelete. SQLite does not enforce FKs inside the suite transaction,
| so enforcement is proven here on real Postgres.
|
| Run the lane:
|   php artisan test --compact --group=pgsql tests/Pgsql/PricingCategoryForeignKeysPostgresTest.php
|
*/

uses(UsesPostgres::class)->group('pgsql');

it('nulls price_category_id when the price category is deleted (nullOnDelete)', function () {
    $priceCategory = PriceCategory::factory()->create();

    $member = Member::factory()->organisation()->create([
        'price_category_id' => $priceCategory->id,
    ]);

    PriceCategory::query()->whereKey($priceCategory->id)->delete();

    expect($member->fresh()->price_category_id)->toBeNull();
});

it('nulls discount_category_id when the discount category is force-deleted (nullOnDelete)', function () {
    $discountCategory = DiscountCategory::factory()->create();

    $member = Member::factory()->organisation()->create([
        'discount_category_id' => $discountCategory->id,
    ]);

    $discountCategory->forceDelete();

    expect($member->fresh()->discount_category_id)->toBeNull();
});
