<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read-optimised projection of event-sourced credit notes.
 *
 * Money columns are INTEGER minor units (negative values mirror the credit).
 * The PK is application-assigned via SequenceAllocator and baked into the
 * CreditNoteIssued event payload for replay-stable projections.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();

            $table->unsignedBigInteger('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();

            $table->string('number', 50);
            $table->string('status', 30);

            $table->integer('subtotal')->default(0);
            $table->integer('tax_total')->default(0);
            $table->integer('total')->default(0);

            $table->string('currency_code', 3);
            $table->string('exchange_rate', 32)->default('1');
            $table->boolean('prices_include_tax')->default(false);

            $table->text('notes')->nullable();

            $table->dateTime('issued_at');

            $table->timestamps();
            $table->softDeletes();

            $table->index('number');
            $table->index('status');
            $table->index('invoice_id');
            $table->unique(['store_id', 'number'], 'credit_notes_store_id_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
