<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tax-rate breakdown rows projected from CreditNoteIssued events.
 *
 * Each row references the original invoice tax row with negated amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_taxes', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();

            $table->unsignedBigInteger('credit_note_id');
            $table->foreign('credit_note_id')->references('id')->on('credit_notes')->cascadeOnDelete();

            $table->unsignedBigInteger('invoice_tax_id');
            $table->foreign('invoice_tax_id')->references('id')->on('invoice_taxes')->cascadeOnDelete();

            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();

            $table->integer('taxable_amount')->default(0);
            $table->integer('tax_amount')->default(0);

            $table->timestamp('created_at')->useCurrent();

            $table->index('credit_note_id');
            $table->index('invoice_tax_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_taxes');
    }
};
