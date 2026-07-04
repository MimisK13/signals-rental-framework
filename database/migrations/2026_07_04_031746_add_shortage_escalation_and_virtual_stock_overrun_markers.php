<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Side-effect markers for scheduled shortage escalation and virtual-stock
     * overrun notifications (P47). Derived conditions stay computed; these columns
     * only record that the notification pipeline has already fired once.
     */
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dateTime('shortage_escalated_at')->nullable()->after('returned_quantity');
        });

        Schema::table('virtual_stock_intakes', function (Blueprint $table): void {
            $table->dateTime('overrun_notified_at')->nullable()->after('returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_stock_intakes', function (Blueprint $table): void {
            $table->dropColumn('overrun_notified_at');
        });

        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropColumn('shortage_escalated_at');
        });
    }
};
