<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreign('sale_tax_category_id')->references('id')->on('tax_categories')->nullOnDelete();
            $table->foreign('purchase_tax_category_id')->references('id')->on('tax_categories')->nullOnDelete();
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
        });
    }
};
