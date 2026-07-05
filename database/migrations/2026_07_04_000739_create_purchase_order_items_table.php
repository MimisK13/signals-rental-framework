<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items projected from purchase order Verbs events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();

            $table->unsignedBigInteger('purchase_order_id');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();

            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('received_quantity', 10, 2)->default(0);

            $table->integer('unit_cost')->default(0);
            $table->integer('tax')->default(0);

            $table->foreignId('product_tax_class_id')->nullable()->constrained('product_tax_classes')->nullOnDelete();

            $table->integer('total')->default(0);

            $table->foreignId('supplier_cost_id')->nullable()->constrained('product_supplier_costs')->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
