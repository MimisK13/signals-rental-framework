<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->integer('price_weight')->default(0)->after('next_retry_at');
            $table->integer('weighted_units')->default(0)->after('price_weight');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropColumn(['price_weight', 'weighted_units']);
        });
    }
};
