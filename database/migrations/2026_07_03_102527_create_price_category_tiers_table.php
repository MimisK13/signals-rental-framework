<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_category_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_category_id')->constrained('price_categories')->cascadeOnDelete();
            $table->unsignedInteger('tier_number');
            $table->string('label')->nullable();
            $table->decimal('multiplier', 10, 4)->default('1.0000');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['price_category_id', 'tier_number']);
            $table->index(['price_category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_category_tiers');
    }
};
