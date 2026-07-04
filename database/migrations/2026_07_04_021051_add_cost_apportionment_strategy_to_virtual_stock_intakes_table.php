<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_stock_intakes', function (Blueprint $table): void {
            $table->string('cost_apportionment_strategy', 50)->nullable()->after('currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_stock_intakes', function (Blueprint $table): void {
            $table->dropColumn('cost_apportionment_strategy');
        });
    }
};
