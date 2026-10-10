<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->string('ownership_type', 30)->default('unknown');
            $table->foreignId('owner_member_id')->nullable()->constrained('members')->nullOnDelete();

            $table->dateTime('acquired_at')->nullable();
            $table->integer('acquisition_amount')->nullable();
            $table->string('acquisition_currency_code', 3)->nullable();
            $table->string('acquisition_exchange_rate', 32)->nullable();
            $table->string('base_currency_code', 3)->nullable();
            $table->integer('base_acquisition_amount')->nullable();
            $table->foreignId('supplier_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();

            $table->integer('estimated_resale_value')->nullable();
            $table->string('estimated_resale_currency_code', 3)->nullable();

            $table->timestamps();

            $table->index('product_id');
            $table->index('ownership_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
