<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktake_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stocktake_id')->constrained('stocktakes')->restrictOnDelete();
            $table->foreignId('stock_level_id')->nullable()->constrained('stock_levels')->nullOnDelete();
            $table->string('product_name_snapshot');
            $table->string('stock_level_label_snapshot')->nullable();
            $table->decimal('quantity_held_snapshot', 10, 2);
            $table->foreignId('accepted_observation_id')->nullable();
            $table->timestamps();

            $table->unique(['stocktake_id', 'stock_level_id']);
            $table->index('accepted_observation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_lines');
    }
};
