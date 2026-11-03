<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->foreign('price_category_tier_id')->references('id')->on('price_category_tiers')->nullOnDelete();
            $table->index('price_category_tier_id');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropIndex(['price_category_tier_id']);
            $table->dropForeign(['price_category_tier_id']);
        });
    }
};
