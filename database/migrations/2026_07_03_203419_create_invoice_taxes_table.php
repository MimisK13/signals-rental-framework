<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tax-rate breakdown rows projected from invoice tax events.
 *
 * Money columns are INTEGER minor units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_taxes', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();

            $table->unsignedBigInteger('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();

            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();

            $table->integer('taxable_amount')->default(0);
            $table->integer('tax_amount')->default(0);

            $table->timestamp('created_at')->useCurrent();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_taxes');
    }
};
