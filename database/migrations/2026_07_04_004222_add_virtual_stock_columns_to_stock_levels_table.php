<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->boolean('is_virtual')->default(false)->after('quantity_on_order');
            $table->unsignedBigInteger('virtual_stock_intake_id')->nullable()->after('is_virtual');
            $table->foreign('virtual_stock_intake_id')
                ->references('id')
                ->on('virtual_stock_intakes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->dropForeign(['virtual_stock_intake_id']);
            $table->dropColumn(['is_virtual', 'virtual_stock_intake_id']);
        });
    }
};
