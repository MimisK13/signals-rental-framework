<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items projected from CreditNoteIssued events.
 *
 * Each row references the original invoice line and stores negated amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();

            $table->unsignedBigInteger('credit_note_id');
            $table->foreign('credit_note_id')->references('id')->on('credit_notes')->cascadeOnDelete();

            $table->unsignedBigInteger('invoice_item_id');
            $table->foreign('invoice_item_id')->references('id')->on('invoice_items')->cascadeOnDelete();

            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->integer('unit_price')->default(0);
            $table->integer('discount')->default(0);
            $table->integer('tax')->default(0);
            $table->integer('total')->default(0);

            $table->string('tax_treatment', 30)->default('standard');
            $table->foreignId('product_tax_class_id')->nullable()->constrained('product_tax_classes')->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('credit_note_id');
            $table->index('invoice_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
    }
};
