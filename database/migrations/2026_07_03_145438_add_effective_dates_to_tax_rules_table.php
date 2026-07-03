<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->date('effective_from')->nullable()->after('priority');
            $table->date('effective_to')->nullable()->after('effective_from');
        });
    }

    public function down(): void
    {
        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->dropColumn(['effective_from', 'effective_to']);
        });
    }
};
