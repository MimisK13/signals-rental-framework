<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantity this invoice line represents (P36 partial-quantity invoicing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->decimal('invoiced_quantity', 10, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn('invoiced_quantity');
        });
    }
};
