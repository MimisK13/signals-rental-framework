<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Running total of quantity invoiced against each opportunity line (P36).
 * Maintained by InvoiceGenerated / InvoiceVoided event projections.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->decimal('invoiced_quantity', 10, 2)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropColumn('invoiced_quantity');
        });
    }
};
