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
        Schema::create('repairs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 30)->default('open');
            $table->dateTime('reported_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('expected_completed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->boolean('is_usable')->default(true);
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('external_repairer_id')->nullable()->constrained('members')->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'store_id', 'status']);
            $table->index(['asset_id', 'status']);
            $table->index(['reported_at', 'completed_at']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repairs');
    }
};
