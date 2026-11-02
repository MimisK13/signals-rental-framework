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
            $table->foreign('active_version_id')->references('id')->on('quotation_versions')->nullOnDelete();
            $table->foreign('accepted_version_id')->references('id')->on('quotation_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropForeign(['active_version_id']);
            $table->dropForeign(['accepted_version_id']);
        });
    }
};
