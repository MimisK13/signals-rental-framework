<?php

use App\Models\DiscountCategory;
use App\Models\DiscountRule;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesPostgres;

/*
| PostgreSQL lane: JSONB criteria storage + GIN index for discount rule queries.
|
|   php artisan test --compact tests/Pgsql/DiscountRuleCriteriaPostgresTest.php
*/

uses(UsesPostgres::class)->group('pgsql');

describe('discount_rules JSONB criteria (pgsql)', function () {
    it('creates discount_rules with jsonb criteria and GIN index on postgres', function () {
        expect(Schema::hasTable('discount_rules'))->toBeTrue()
            ->and(Schema::hasColumn('discount_rules', 'criteria'))->toBeTrue();

        $indexes = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'discount_rules'"))
            ->pluck('indexname');

        expect($indexes)->toContain('discount_rules_criteria_gin_idx');
    });

    it('queries rules by jsonb containment on product_ids', function () {
        $category = DiscountCategory::factory()->create();
        $product = Product::factory()->create();

        DiscountRule::factory()->for($category)->create([
            'criteria' => ['product_ids' => [$product->id]],
            'discount_percent' => '10.00',
        ]);

        DiscountRule::factory()->for($category)->create([
            'criteria' => ['product_ids' => [999999]],
            'discount_percent' => '5.00',
        ]);

        $matches = DiscountRule::query()
            ->whereRaw('criteria @> ?::jsonb', [json_encode(['product_ids' => [$product->id]])])
            ->pluck('discount_percent')
            ->map(fn ($value): string => (string) $value)
            ->all();

        expect($matches)->toBe(['10.00']);
    });

    it('queries rules by jsonb key existence for tag_names', function () {
        $category = DiscountCategory::factory()->create();

        DiscountRule::factory()->for($category)->create([
            'criteria' => ['tag_names' => ['lighting']],
        ]);

        DiscountRule::factory()->for($category)->create([
            'criteria' => ['product_group_ids' => [1]],
        ]);

        $count = DiscountRule::query()
            ->whereRaw("criteria ?? 'tag_names'")
            ->count();

        expect($count)->toBe(1);
    });
});
