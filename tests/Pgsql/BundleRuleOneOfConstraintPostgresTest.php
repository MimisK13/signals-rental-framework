<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesPostgres;

/*
| PostgreSQL lane: bundle_rules one-of check constraint (percent XOR fixed).
|
|   php artisan test --compact --group=pgsql tests/Pgsql/BundleRuleOneOfConstraintPostgresTest.php
*/

uses(UsesPostgres::class)->group('pgsql');

describe('bundle_rules value one-of constraint (pgsql)', function () {
    it('allows percent-only bundle rules', function () {
        $id = DB::table('bundle_rules')->insertGetId([
            'name' => 'Percent only',
            'bundle_percent' => 10.00,
            'bundle_fixed_price_minor' => null,
            'criteria' => json_encode([]),
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($id)->toBeGreaterThan(0);
    });

    it('allows fixed-only bundle rules', function () {
        $id = DB::table('bundle_rules')->insertGetId([
            'name' => 'Fixed only',
            'bundle_percent' => null,
            'bundle_fixed_price_minor' => 15_000,
            'criteria' => json_encode([]),
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($id)->toBeGreaterThan(0);
    });

    it('rejects bundle rules with both percent and fixed values', function () {
        expect(fn () => DB::table('bundle_rules')->insert([
            'name' => 'Both',
            'bundle_percent' => 10.00,
            'bundle_fixed_price_minor' => 1000,
            'criteria' => json_encode([]),
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    it('rejects bundle rules with neither percent nor fixed value', function () {
        expect(fn () => DB::table('bundle_rules')->insert([
            'name' => 'Neither',
            'bundle_percent' => null,
            'bundle_fixed_price_minor' => null,
            'criteria' => json_encode([]),
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    it('declares the bundle_rules_value_one_of_check constraint on postgres', function () {
        expect(Schema::hasTable('bundle_rules'))->toBeTrue();

        $constraints = collect(DB::select(
            "SELECT conname FROM pg_constraint WHERE conrelid = 'bundle_rules'::regclass",
        ))->pluck('conname');

        expect($constraints)->toContain('bundle_rules_value_one_of_check');
    });
});
