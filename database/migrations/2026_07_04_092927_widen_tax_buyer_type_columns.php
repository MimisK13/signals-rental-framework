<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->string('buyer_type', 16)->default('business')->change();
        });

        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->string('buyer_type', 16)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->string('buyer_type', 8)->default('b2b')->change();
        });

        Schema::table('tax_rules', function (Blueprint $table): void {
            $table->string('buyer_type', 8)->nullable()->change();
        });
    }
};
