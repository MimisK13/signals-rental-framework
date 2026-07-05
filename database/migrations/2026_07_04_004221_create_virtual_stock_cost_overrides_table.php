<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-opportunity unit cost overrides for virtual stock intakes (P45 apportionment seam).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_stock_cost_overrides', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('virtual_stock_intake_id');
            $table->foreign('virtual_stock_intake_id')
                ->references('id')
                ->on('virtual_stock_intakes')
                ->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->integer('unit_cost_override');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(
                ['virtual_stock_intake_id', 'opportunity_id'],
                'uniq_virtual_stock_cost_overrides_intake_opportunity',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_stock_cost_overrides');
    }
};
