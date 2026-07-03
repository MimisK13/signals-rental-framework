<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->boolean('is_discount_manual')->default(false)->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropColumn('is_discount_manual');
        });
    }
};
