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
        Schema::create('supplier_bill_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_bill_id')->constrained('supplier_bills')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('virtual_stock_intake_id')->nullable()->constrained('virtual_stock_intakes')->nullOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->integer('unit_cost')->default(0);
            $table->integer('tax')->default(0);
            $table->integer('total')->default(0);
            $table->timestamps();

            $table->index('supplier_bill_id');
            $table->index('product_id');
            $table->index('purchase_order_item_id');
            $table->index('virtual_stock_intake_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_bill_items');
    }
};
