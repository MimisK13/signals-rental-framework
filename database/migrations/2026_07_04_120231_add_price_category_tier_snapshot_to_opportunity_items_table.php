<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->foreignId('price_category_tier_id')
                ->nullable()
                ->after('unit_price')
                ->constrained('price_category_tiers')
                ->nullOnDelete();

            $table->decimal('price_category_tier_multiplier', 10, 4)->nullable()->after('price_category_tier_id');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('price_category_tier_id');
            $table->dropColumn('price_category_tier_multiplier');
        });
    }
};
