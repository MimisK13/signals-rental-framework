<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->foreign('seller_zone_id')->references('id')->on('tax_zones')->nullOnDelete();
            $table->foreign('buyer_zone_id')->references('id')->on('tax_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->dropForeign(['seller_zone_id']);
            $table->dropForeign(['buyer_zone_id']);
        });
    }
};
