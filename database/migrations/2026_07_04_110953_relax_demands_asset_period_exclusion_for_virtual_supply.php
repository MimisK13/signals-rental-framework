<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Exclude virtual per-asset supply rows from the serialised asset-period exclusion.
 *
 * Virtual serialised supply is modelled as an active demand with quantity -1 on
 * the asset_id; allocation adds a quantity 1 demand for the same asset. Both
 * must coexist without tripping excl_demands_asset_period — only positive
 * (allocation) demands remain mutually exclusive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE demands DROP CONSTRAINT IF EXISTS excl_demands_asset_period');

        DB::statement(<<<'SQL'
            ALTER TABLE demands
              ADD CONSTRAINT excl_demands_asset_period
              EXCLUDE USING gist (asset_id WITH =, period WITH &&)
              WHERE (asset_id IS NOT NULL AND is_active = true AND quantity > 0)
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE demands DROP CONSTRAINT IF EXISTS excl_demands_asset_period');

        DB::statement(<<<'SQL'
            ALTER TABLE demands
              ADD CONSTRAINT excl_demands_asset_period
              EXCLUDE USING gist (asset_id WITH =, period WITH &&)
              WHERE (asset_id IS NOT NULL AND is_active = true)
        SQL);
    }
};
