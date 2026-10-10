<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->decimal('reserved_quantity', 10, 2)->nullable()->after('quantity');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE opportunity_items
                ADD CONSTRAINT opportunity_items_reserved_quantity_non_negative
                CHECK (reserved_quantity IS NULL OR reserved_quantity >= 0)
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE opportunity_items
                ADD CONSTRAINT opportunity_items_reserved_quantity_not_above_quantity
                CHECK (reserved_quantity IS NULL OR reserved_quantity <= quantity)
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE opportunity_items DROP CONSTRAINT IF EXISTS opportunity_items_reserved_quantity_non_negative');
            DB::statement('ALTER TABLE opportunity_items DROP CONSTRAINT IF EXISTS opportunity_items_reserved_quantity_not_above_quantity');
        }

        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropColumn('reserved_quantity');
        });
    }
};
