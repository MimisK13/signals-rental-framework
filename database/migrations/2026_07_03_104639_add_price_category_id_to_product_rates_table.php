<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_rates', function (Blueprint $table): void {
            $table->dropIndex(['product_id', 'store_id', 'transaction_type', 'priority']);

            $table->foreignId('price_category_id')
                ->nullable()
                ->after('store_id')
                ->constrained('price_categories')
                ->nullOnDelete();

            $table->index(
                ['product_id', 'store_id', 'transaction_type', 'price_category_id', 'priority'],
                'product_rates_resolution_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('product_rates', function (Blueprint $table): void {
            $table->dropIndex('product_rates_resolution_index');
            $table->dropConstrainedForeignId('price_category_id');

            $table->index(['product_id', 'store_id', 'transaction_type', 'priority']);
        });
    }
};
