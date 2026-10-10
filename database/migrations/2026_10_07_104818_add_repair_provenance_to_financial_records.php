<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_expenses', function (Blueprint $table): void {
            $table->foreignId('repair_id')->nullable()->constrained('repairs')->restrictOnDelete();
            $table->unique('repair_id', 'asset_expenses_repair_unique');
        });

        Schema::table('supplier_bill_items', function (Blueprint $table): void {
            $table->foreignId('repair_id')->nullable()->constrained('repairs')->restrictOnDelete();
            $table->index('repair_id');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_bill_items', function (Blueprint $table): void {
            $table->dropForeign(['repair_id']);
            $table->dropIndex(['repair_id']);
            $table->dropColumn('repair_id');
        });

        Schema::table('asset_expenses', function (Blueprint $table): void {
            $table->dropUnique('asset_expenses_repair_unique');
            $table->dropForeign(['repair_id']);
            $table->dropColumn('repair_id');
        });

    }
};
