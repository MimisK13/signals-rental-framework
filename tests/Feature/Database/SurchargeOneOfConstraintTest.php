<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('rejects surcharges that set both percent and fixed values on postgres', function () {
    if (Schema::getConnection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('One-of constraint is enforced on PostgreSQL only.');
    }

    $groupId = DB::table('surcharge_groups')->insertGetId([
        'name' => 'Invalid',
        'is_default' => false,
        'is_active' => true,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

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

it('rejects surcharges with neither percent nor fixed value on postgres', function () {
    if (Schema::getConnection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('One-of constraint is enforced on PostgreSQL only.');
    }

    $groupId = DB::table('surcharge_groups')->insertGetId([
        'name' => 'Invalid',
        'is_default' => false,
        'is_active' => true,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

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
