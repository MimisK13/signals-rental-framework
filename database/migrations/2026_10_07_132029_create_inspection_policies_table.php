<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inspection_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('interval_months');
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'name']);
            $table->index(['product_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_policies');
    }
};
