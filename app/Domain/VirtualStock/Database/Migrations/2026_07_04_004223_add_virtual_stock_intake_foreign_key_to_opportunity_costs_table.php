<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_costs', function (Blueprint $table): void {
            $table->foreign('virtual_stock_intake_id')->references('id')->on('virtual_stock_intakes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_costs', function (Blueprint $table): void {
            $table->dropForeign(['virtual_stock_intake_id']);
        });
    }
};
