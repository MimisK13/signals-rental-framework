<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_rules', function (Blueprint $table): void {
            $table->string('rule_scope', 32)->default('per_line')->after('criteria');
            $table->index(['discount_category_id', 'rule_scope']);
        });
    }

    public function down(): void
    {
        Schema::table('discount_rules', function (Blueprint $table): void {
            $table->dropIndex(['discount_category_id', 'rule_scope']);
            $table->dropColumn('rule_scope');
        });
    }
};
