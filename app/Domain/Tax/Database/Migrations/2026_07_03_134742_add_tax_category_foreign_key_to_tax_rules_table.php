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
            $table->foreign('tax_category_id')->references('id')->on('tax_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->dropForeign(['tax_category_id']);
        });
    }
};
