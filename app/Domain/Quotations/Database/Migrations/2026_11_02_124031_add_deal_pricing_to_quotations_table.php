<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->bigInteger('deal_total')->nullable()->after('charge_total');
            $table->string('deal_price_mode', 32)->default('flat_headline')->after('deal_total');
            $table->bigInteger('deal_price_original_total')->nullable()->after('deal_price_mode');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropColumn(['deal_total', 'deal_price_mode', 'deal_price_original_total']);
        });
    }
};
