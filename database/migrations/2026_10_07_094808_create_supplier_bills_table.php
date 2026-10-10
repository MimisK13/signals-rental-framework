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
        Schema::create('supplier_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('document_number', 100);
            $table->string('status', 30)->default('draft');
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->integer('subtotal')->default(0);
            $table->integer('tax_total')->default(0);
            $table->integer('total')->default(0);
            $table->string('currency_code', 3);
            $table->string('exchange_rate', 32)->default('1');
            $table->text('notes')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
            $table->index(['status', 'issued_at']);
            $table->unique(['supplier_id', 'document_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_bills');
    }
};
