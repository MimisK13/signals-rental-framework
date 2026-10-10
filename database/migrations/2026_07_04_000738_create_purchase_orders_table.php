<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read-optimised projection of event-sourced purchase orders.
 *
 * Money columns are INTEGER minor units. The PK is application-assigned via
 * SequenceAllocator and baked into the PurchaseOrderCreated event payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('state_id')->unique();

            $table->string('number', 50);
            $table->string('po_type', 30);
            $table->string('status', 30);

            $table->foreignId('supplier_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->nullOnDelete();

            $table->unsignedBigInteger('shortage_resolution_id')->nullable();
            $table->foreign('shortage_resolution_id')->references('id')->on('shortage_resolutions')->nullOnDelete();

            $table->string('currency_code', 3);
            $table->string('exchange_rate', 32)->default('1');

            $table->integer('subtotal')->default(0);
            $table->integer('tax_total')->default(0);
            $table->integer('total')->default(0);
            $table->integer('rounding_adjustment')->default(0);

            $table->dateTime('expected_at')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();

            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('supplier_reference')->nullable();

            $table->dateTime('sent_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('number');
            $table->index('po_type');
            $table->index('status');
            $table->index('supplier_id');
            $table->index('opportunity_id');
            $table->index(['store_id', 'status']);
            $table->unique(['store_id', 'number'], 'purchase_orders_store_id_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
