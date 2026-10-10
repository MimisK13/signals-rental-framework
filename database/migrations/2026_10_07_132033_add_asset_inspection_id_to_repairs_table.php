<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repairs', function (Blueprint $table): void {
            $table->foreignId('asset_inspection_id')->nullable()->constrained('asset_inspections')->restrictOnDelete();
            $table->index('asset_inspection_id');
        });
    }

    public function down(): void
    {
        Schema::table('repairs', function (Blueprint $table): void {
            $table->dropForeign(['asset_inspection_id']);
            $table->dropIndex(['asset_inspection_id']);
            $table->dropColumn('asset_inspection_id');
        });
    }
};
