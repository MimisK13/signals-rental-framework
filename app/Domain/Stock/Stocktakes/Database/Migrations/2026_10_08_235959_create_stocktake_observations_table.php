<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_observations');
    }
};
