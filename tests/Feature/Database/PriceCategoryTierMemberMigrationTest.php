<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesIsolatedSqliteMigrations;

uses(UsesIsolatedSqliteMigrations::class);

const PRICE_CATEGORY_TIER_MEMBER_MIGRATION = '2026_07_04_120230_add_price_category_tier_id_to_members_table';

describe('price category tier member migration (sqlite)', function () {
    it('adds price_category_tier_id to members', function () {
        expect(Schema::hasColumn('members', 'price_category_tier_id'))->toBeTrue();
    });

    it('rolls back and re-applies without leaving a stale index on sqlite', function () {
        $steps = DB::table('migrations')
            ->where('migration', '>=', PRICE_CATEGORY_TIER_MEMBER_MIGRATION)
            ->count();

        Artisan::call('migrate:rollback', [
            '--step' => $steps,
            '--force' => true,
        ]);

        expect(Schema::hasColumn('members', 'price_category_tier_id'))->toBeFalse();

        Artisan::call('migrate', [
            '--force' => true,
        ]);

        expect(Schema::hasColumn('members', 'price_category_tier_id'))->toBeTrue();
    });
});
