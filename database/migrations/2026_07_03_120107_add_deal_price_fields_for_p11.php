<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('deal_price_mode', 32)->default('flat_headline')->after('deal_total');
            $table->integer('deal_price_original_total')->nullable()->after('deal_price_mode');
        });

        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->decimal('deal_price_discount_percent', 5, 2)->nullable()->after('is_discount_manual');
            $table->integer('deal_price')->nullable()->after('deal_price_discount_percent');
            $table->integer('deal_price_original_total')->nullable()->after('deal_price');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropColumn([
                'deal_price_discount_percent',
                'deal_price',
                'deal_price_original_total',
            ]);
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropColumn([
                'deal_price_mode',
                'deal_price_original_total',
            ]);
        });
    }
};
