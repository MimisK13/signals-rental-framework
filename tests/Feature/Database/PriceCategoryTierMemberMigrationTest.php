<?php

use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesIsolatedSqliteMigrations;

uses(UsesIsolatedSqliteMigrations::class);

const PRICE_CATEGORY_TIER_MEMBER_MIGRATION = 'app/Domain/Pricing/Database/Migrations/2026_07_03_102527_link_members_to_price_category_tiers_table.php';

describe('price category tier member link migration (sqlite)', function () {
    it('links price_category_tier_id on members', function () {
        $indexes = collect(Schema::getIndexes('members'))->pluck('name');

        expect(Schema::hasColumn('members', 'price_category_tier_id'))->toBeTrue()
            ->and($indexes)->toContain('members_price_category_tier_id_index');
    });

    it('rolls back and re-applies without leaving a stale index on sqlite', function () {
        $migration = require base_path(PRICE_CATEGORY_TIER_MEMBER_MIGRATION);
        $migration->down();

        $indexes = collect(Schema::getIndexes('members'))->pluck('name');

        expect(Schema::hasColumn('members', 'price_category_tier_id'))->toBeTrue()
            ->and($indexes)->not->toContain('members_price_category_tier_id_index');

        $migration->up();

        $indexes = collect(Schema::getIndexes('members'))->pluck('name');

        expect(Schema::hasColumn('members', 'price_category_tier_id'))->toBeTrue()
            ->and($indexes)->toContain('members_price_category_tier_id_index');
    });
});
