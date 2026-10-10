<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->unique('asset_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->dropUnique(['asset_id']);
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
