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
        Schema::create('container_bulk_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('containers')->restrictOnDelete();
            $table->foreignId('stock_level_id')->constrained('stock_levels')->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->timestampsTz();

            $table->unique(['container_id', 'stock_level_id'], 'uq_container_bulk_memberships_container_stock');
            $table->index('stock_level_id', 'idx_container_bulk_memberships_stock_level');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE container_bulk_memberships
                  ADD CONSTRAINT chk_container_bulk_memberships_quantity_positive
                  CHECK (quantity > 0)
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_bulk_memberships');
    }
};
