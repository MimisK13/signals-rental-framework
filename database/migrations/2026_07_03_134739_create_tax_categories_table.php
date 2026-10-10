<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 32)->unique();
            $table->string('type', 32);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreign('sale_tax_category_id')->references('id')->on('tax_categories')->nullOnDelete();
            $table->foreign('purchase_tax_category_id')->references('id')->on('tax_categories')->nullOnDelete();
            $table->index('sale_tax_category_id');
            $table->index('purchase_tax_category_id');
        });

        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->foreign('tax_category_id')->references('id')->on('tax_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->dropForeign(['tax_category_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['sale_tax_category_id']);
            $table->dropForeign(['purchase_tax_category_id']);
            $table->dropIndex(['sale_tax_category_id']);
            $table->dropIndex(['purchase_tax_category_id']);
        });

        Schema::dropIfExists('tax_categories');
    }
};
