<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project frozen tax metadata from InvoiceGenerated event payloads so invoice
 * PDFs can render grouped tax summaries with reverse-charge legal text without
 * replaying events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_taxes', function (Blueprint $table): void {
            $table->string('rate_percentage', 16)->nullable()->after('tax_amount');
            $table->string('tax_rate_name')->nullable()->after('rate_percentage');
            $table->string('result_type', 32)->nullable()->after('tax_rate_name');
            $table->text('legal_text')->nullable()->after('result_type');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_taxes', function (Blueprint $table): void {
            $table->dropColumn(['rate_percentage', 'tax_rate_name', 'result_type', 'legal_text']);
        });
    }
};
