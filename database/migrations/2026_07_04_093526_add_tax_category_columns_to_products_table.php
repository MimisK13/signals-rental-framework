<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('sale_tax_category_id')
                ->nullable()
                ->after('purchase_tax_class_id')
                ->constrained('tax_categories')
                ->nullOnDelete();
            $table->foreignId('purchase_tax_category_id')
                ->nullable()
                ->after('sale_tax_category_id')
                ->constrained('tax_categories')
                ->nullOnDelete();

            $table->index('sale_tax_category_id');
            $table->index('purchase_tax_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['sale_tax_category_id']);
            $table->dropForeign(['purchase_tax_category_id']);
            $table->dropIndex(['sale_tax_category_id']);
            $table->dropIndex(['purchase_tax_category_id']);
            $table->dropColumn(['sale_tax_category_id', 'purchase_tax_category_id']);
        });
    }
};
