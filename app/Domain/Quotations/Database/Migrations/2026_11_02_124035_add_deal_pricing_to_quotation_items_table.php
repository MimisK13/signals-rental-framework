<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table): void {
            $table->decimal('deal_price_discount_percent', 18, 10)->nullable()->after('discount_percent');
            $table->bigInteger('deal_price')->nullable()->after('deal_price_discount_percent');
            $table->bigInteger('deal_price_original_total')->nullable()->after('deal_price');
            $table->bigInteger('deal_price_original_tax_total')->nullable()->after('deal_price_original_total');
            $table->bigInteger('deal_price_original_gross_total')->nullable()->after('deal_price_original_tax_total');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table): void {
            $table->dropColumn([
                'deal_price_discount_percent',
                'deal_price',
                'deal_price_original_total',
                'deal_price_original_tax_total',
                'deal_price_original_gross_total',
            ]);
        });
    }
};
