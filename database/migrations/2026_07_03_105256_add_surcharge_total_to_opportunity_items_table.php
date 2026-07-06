<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->integer('surcharge_total')->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropColumn('surcharge_total');
        });
    }
};
