<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shortage_resolutions', function (Blueprint $table): void {
            $table->unsignedBigInteger('virtual_stock_intake_id')->nullable()->after('notes');
            $table->foreign('virtual_stock_intake_id')
                ->references('id')
                ->on('virtual_stock_intakes')
                ->nullOnDelete();
        });

        Schema::table('opportunity_costs', function (Blueprint $table): void {
            $table->unsignedBigInteger('virtual_stock_intake_id')->nullable()->after('opportunity_id');
            $table->foreign('virtual_stock_intake_id')
                ->references('id')
                ->on('virtual_stock_intakes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shortage_resolutions', function (Blueprint $table): void {
            $table->dropForeign(['virtual_stock_intake_id']);
            $table->dropColumn('virtual_stock_intake_id');
        });

        Schema::table('opportunity_costs', function (Blueprint $table): void {
            $table->dropForeign(['virtual_stock_intake_id']);
            $table->dropColumn('virtual_stock_intake_id');
        });
    }
};
