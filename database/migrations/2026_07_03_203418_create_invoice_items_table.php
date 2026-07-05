<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read-optimised projection of invoice line items.
 *
 * Money columns are INTEGER minor units. Snapshot columns preserve the monetary
 * and tax context at line creation time for replay-stable projections.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();

            $table->unsignedBigInteger('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();

            $table->unsignedBigInteger('opportunity_item_id')->nullable();
            $table->foreign('opportunity_item_id')->references('id')->on('opportunity_items')->nullOnDelete();

            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->integer('unit_price')->default(0);
            $table->integer('discount')->default(0);
            $table->integer('tax')->default(0);
            $table->integer('total')->default(0);

            $table->string('tax_treatment', 30)->default('standard');
            $table->foreignId('product_tax_class_id')->nullable()->constrained('product_tax_classes')->nullOnDelete();

            $table->string('currency_code', 3);
            $table->string('exchange_rate', 32)->default('1');
            $table->boolean('prices_include_tax')->default(false);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('invoice_id');
            $table->index('opportunity_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
