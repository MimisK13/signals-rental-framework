<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_versions', function (Blueprint $table): void {
            $table->foreign('parent_version_id')->references('id')->on('quotation_versions')->nullOnDelete();
            $table->foreign('superseded_by_version_id')->references('id')->on('quotation_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_versions', function (Blueprint $table): void {
            $table->dropForeign(['parent_version_id']);
            $table->dropForeign(['superseded_by_version_id']);
        });
    }
};
