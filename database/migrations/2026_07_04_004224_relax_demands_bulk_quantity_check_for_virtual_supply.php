<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allow negative bulk demand quantities for virtual stock supply rows.
 *
 * Follows the driver-gating pattern from create_demands_table: the CHECK is
 * enforced on PostgreSQL only; SQLite tests rely on application validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE demands DROP CONSTRAINT IF EXISTS chk_demands_bulk_quantity');

        DB::statement(<<<'SQL'
            ALTER TABLE demands
              ADD CONSTRAINT chk_demands_bulk_quantity
              CHECK (asset_id IS NOT NULL OR quantity >= 1 OR quantity <= -1)
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE demands DROP CONSTRAINT IF EXISTS chk_demands_bulk_quantity');

        DB::statement(<<<'SQL'
            ALTER TABLE demands
              ADD CONSTRAINT chk_demands_bulk_quantity
              CHECK (asset_id IS NOT NULL OR quantity >= 1)
        SQL);
    }
};
