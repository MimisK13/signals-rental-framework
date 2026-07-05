<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->foreign('price_category_id')
                ->references('id')
                ->on('price_categories')
                ->nullOnDelete();

            $table->foreign('discount_category_id')
                ->references('id')
                ->on('discount_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropForeign(['price_category_id']);
            $table->dropForeign(['discount_category_id']);
        });
    }
};
