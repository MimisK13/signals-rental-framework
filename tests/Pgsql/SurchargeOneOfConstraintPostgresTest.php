<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesPostgres;

/*
| PostgreSQL lane: surcharges one-of check constraint (percent XOR fixed).
|
|   php artisan test --compact --group=pgsql tests/Pgsql/SurchargeOneOfConstraintPostgresTest.php
*/

uses(UsesPostgres::class)->group('pgsql');

describe('surcharges value one-of constraint (pgsql)', function () {
    it('allows percent-only surcharges', function () {
        $groupId = insertSurchargeGroup();

        $id = DB::table('surcharges')->insertGetId([
            'surcharge_group_id' => $groupId,
            'name' => 'Percent only',
            'percent_value' => 5.00,
            'fixed_value_minor' => null,
            'criteria' => json_encode([]),
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($id)->toBeGreaterThan(0);
    });

    it('allows fixed-only surcharges', function () {
        $groupId = insertSurchargeGroup();

        $id = DB::table('surcharges')->insertGetId([
            'surcharge_group_id' => $groupId,
            'name' => 'Fixed only',
            'percent_value' => null,
            'fixed_value_minor' => 250,
            'criteria' => json_encode([]),
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($id)->toBeGreaterThan(0);
    });

    it('rejects surcharges with both percent and fixed values', function () {
        $groupId = insertSurchargeGroup();

        expect(fn () => DB::table('surcharges')->insert([
            'surcharge_group_id' => $groupId,
            'name' => 'Both',
            'percent_value' => 5.00,
            'fixed_value_minor' => 100,
            'criteria' => json_encode([]),
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    it('rejects surcharges with neither percent nor fixed value', function () {
        $groupId = insertSurchargeGroup();

        expect(fn () => DB::table('surcharges')->insert([
            'surcharge_group_id' => $groupId,
            'name' => 'Neither',
            'percent_value' => null,
            'fixed_value_minor' => null,
            'criteria' => json_encode([]),
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    it('declares the surcharges_value_one_of_check constraint on postgres', function () {
        expect(Schema::hasTable('surcharges'))->toBeTrue();

        $constraints = collect(DB::select(
            "SELECT conname FROM pg_constraint WHERE conrelid = 'surcharges'::regclass",
        ))->pluck('conname');

        expect($constraints)->toContain('surcharges_value_one_of_check');
    });
});

function insertSurchargeGroup(): int
{
    return DB::table('surcharge_groups')->insertGetId([
        'name' => 'Constraint lane',
        'is_default' => false,
        'is_active' => true,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
