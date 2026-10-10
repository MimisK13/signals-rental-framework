<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktakes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('status', 30)->index();
            $table->foreignId('started_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

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

        Schema::create('stocktake_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stocktake_id')->constrained('stocktakes')->restrictOnDelete();
            $table->foreignId('stocktake_line_id')->constrained('stocktake_lines')->restrictOnDelete();
            $table->decimal('counted_quantity', 10, 2);
            $table->foreignId('counted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['stocktake_id', 'stocktake_line_id']);
            $table->index(['counted_by_user_id', 'counted_at']);
        });

        Schema::table('stocktake_lines', function (Blueprint $table): void {
            $table->foreign('accepted_observation_id')
                ->references('id')
                ->on('stocktake_observations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_observations');
        Schema::dropIfExists('stocktake_lines');
        Schema::dropIfExists('stocktakes');
    }
};
