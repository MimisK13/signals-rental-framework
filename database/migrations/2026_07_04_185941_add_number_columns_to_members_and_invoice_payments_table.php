<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->string('number', 50)->nullable()->after('id')->index();
        });

        Schema::table('invoice_payments', function (Blueprint $table): void {
            $table->string('number', 50)->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropIndex(['number']);
            $table->dropColumn('number');
        });

        Schema::table('invoice_payments', function (Blueprint $table): void {
            $table->dropIndex(['number']);
            $table->dropColumn('number');
        });
    }
};
