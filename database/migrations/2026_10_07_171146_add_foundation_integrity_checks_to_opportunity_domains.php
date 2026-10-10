<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE opportunities
            ADD CONSTRAINT opportunities_planning_period_valid
            CHECK (
                planning_starts_at IS NULL
                OR planning_ends_at IS NULL
                OR planning_starts_at < planning_ends_at
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE opportunity_items
            ADD CONSTRAINT opportunity_items_planning_period_valid
            CHECK (
                planning_starts_at IS NULL
                OR planning_ends_at IS NULL
                OR planning_starts_at < planning_ends_at
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE opportunity_items
            ADD CONSTRAINT opportunity_items_quantity_non_negative
            CHECK (quantity >= 0)
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE opportunity_items DROP CONSTRAINT opportunity_items_planning_period_valid');
        DB::statement('ALTER TABLE opportunity_items DROP CONSTRAINT opportunity_items_quantity_non_negative');
        DB::statement('ALTER TABLE opportunities DROP CONSTRAINT opportunities_planning_period_valid');
    }
};
