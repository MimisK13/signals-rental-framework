<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read-optimised projection of event-sourced virtual stock intakes.
 *
 * Virtual stock represents temporary supply (sub-hire) modelled as negative
 * availability demand within a half-open [available_from, available_until) window.
 * The PK is application-assigned via SequenceAllocator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_stock_intakes', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('state_id')->unique();

            $table->string('number', 50);
            $table->string('status', 30);

            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();

            $table->unsignedBigInteger('purchase_order_item_id')->nullable();
            $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->nullOnDelete();

            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            $table->integer('quantity');
            $table->timestampTz('available_from');
            $table->timestampTz('available_until');

            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('returned_at')->nullable();
            $table->dateTime('overrun_notified_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();

            $table->integer('unit_cost');
            $table->string('currency_code', 3);
            $table->string('cost_apportionment_strategy', 50)->nullable();
            $table->unsignedBigInteger('primary_opportunity_id')->nullable();
            $table->foreign('primary_opportunity_id')->references('id')->on('opportunities')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedBigInteger('demand_id')->nullable();
            $table->foreign('demand_id')->references('id')->on('demands')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('number');
            $table->index('status');
            $table->index('product_id');
            $table->index(['store_id', 'status']);
            $table->unique(['store_id', 'number'], 'virtual_stock_intakes_store_id_number_unique');
            $table->index(['available_from', 'available_until']);
            $table->index('primary_opportunity_id');
        });

        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->foreign('virtual_stock_intake_id')->references('id')->on('virtual_stock_intakes')->nullOnDelete();
        });

        Schema::table('shortage_resolutions', function (Blueprint $table): void {
            $table->foreign('virtual_stock_intake_id')->references('id')->on('virtual_stock_intakes')->nullOnDelete();
        });

        Schema::table('opportunity_costs', function (Blueprint $table): void {
            $table->foreign('virtual_stock_intake_id')->references('id')->on('virtual_stock_intakes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table): void {
            $table->dropForeign(['virtual_stock_intake_id']);
        });

        Schema::table('shortage_resolutions', function (Blueprint $table): void {
            $table->dropForeign(['virtual_stock_intake_id']);
        });

        Schema::table('opportunity_costs', function (Blueprint $table): void {
            $table->dropForeign(['virtual_stock_intake_id']);
        });

        Schema::dropIfExists('virtual_stock_intakes');
    }
};
