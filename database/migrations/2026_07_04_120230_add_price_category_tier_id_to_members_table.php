<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->foreignId('price_category_tier_id')
                ->nullable()
                ->after('price_category_id')
                ->constrained('price_category_tiers')
                ->nullOnDelete();

            $table->index('price_category_tier_id');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            // Drop the explicit index before the FK/column: SQLite native DROP COLUMN
            // fails when a separate index still references the column.
            $table->dropIndex(['price_category_tier_id']);
            $table->dropConstrainedForeignId('price_category_tier_id');
        });
    }
};
