<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_supplier_costs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->integer('cost');
            $table->char('currency_code', 3);
            $table->string('cost_basis');
            $table->string('supplier_sku')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->decimal('min_order_quantity', 10, 2)->default('1.00');
            $table->boolean('is_preferred')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // PostgreSQL treats NULL store_id as distinct in unique indexes, so
            // duplicate "all stores" rows are possible at the DB layer. The create/
            // update actions enforce uniqueness with an explicit null-store scope check.
            $table->unique(
                ['product_id', 'supplier_id', 'store_id', 'cost_basis'],
                'product_supplier_costs_unique',
            );

            $table->index(['product_id', 'is_preferred']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_supplier_costs');
    }
};
