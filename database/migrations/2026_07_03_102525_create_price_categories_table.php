<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->foreign('price_category_id')->references('id')->on('price_categories')->nullOnDelete();
        });

        Schema::table('product_rates', function (Blueprint $table): void {
            $table->foreign('price_category_id')->references('id')->on('price_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropForeign(['price_category_id']);
        });

        Schema::table('product_rates', function (Blueprint $table): void {
            $table->dropForeign(['price_category_id']);
        });

        Schema::dropIfExists('price_categories');
    }
};
