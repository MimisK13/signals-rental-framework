<?php

use App\Models\Member;
use App\Models\Store;
use App\Models\TaxZone;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesPostgres;

/*
| PostgreSQL lane: tax zone schema + nullOnDelete FK behaviour on real Postgres.
|
|   php artisan test --compact --group=pgsql tests/Pgsql/TaxZoneMigrationsPostgresTest.php
*/

uses(UsesPostgres::class)->group('pgsql');

describe('tax zone migrations (pgsql)', function () {
    it('creates tax zone tables with zone resolution columns on tax_rules', function () {
        expect(Schema::hasTable('tax_zones'))->toBeTrue()
            ->and(Schema::hasTable('tax_zone_members'))->toBeTrue()
            ->and(Schema::hasTable('tax_categories'))->toBeTrue()
            ->and(Schema::hasColumn('tax_rules', 'seller_zone_id'))->toBeTrue()
            ->and(Schema::hasColumn('tax_rules', 'result_type'))->toBeTrue()
            ->and(Schema::hasColumn('members', 'tax_zone_id'))->toBeTrue()
            ->and(Schema::hasColumn('stores', 'tax_zone_id'))->toBeTrue();
    });

    it('nulls member tax_zone_id when the tax zone is deleted (nullOnDelete)', function () {
        $zone = TaxZone::factory()->create();

        $member = Member::factory()->organisation()->create([
            'tax_zone_id' => $zone->id,
        ]);

        TaxZone::query()->whereKey($zone->id)->delete();

        expect($member->fresh()->tax_zone_id)->toBeNull();
    });

    it('nulls store tax_zone_id when the tax zone is deleted (nullOnDelete)', function () {
        $zone = TaxZone::factory()->create();

        $store = Store::factory()->create([
            'tax_zone_id' => $zone->id,
        ]);

        TaxZone::query()->whereKey($zone->id)->delete();

        expect($store->fresh()->tax_zone_id)->toBeNull();
    });
});
