<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocktake_lines', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('asset_number_snapshot')->nullable();
            $table->string('serial_number_snapshot')->nullable();
            $table->string('barcode_snapshot')->nullable();
            $table->string('lifecycle_status_snapshot')->nullable();
            $table->foreignId('container_id')->nullable()->constrained('containers')->nullOnDelete();
            $table->string('container_name_snapshot')->nullable();
            $table->string('container_barcode_snapshot')->nullable();
            $table->string('repair_status_snapshot')->nullable();
            $table->string('inspection_status_snapshot')->nullable();
            $table->foreignId('asset_usage_id_snapshot')->nullable()->constrained('asset_usages')->nullOnDelete();

            $table->unique(['stocktake_id', 'asset_id']);
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::table('stocktake_lines', function (Blueprint $table): void {
            $table->dropUnique(['stocktake_id', 'asset_id']);
            $table->dropIndex(['asset_id']);
            $table->dropForeign(['asset_id']);
            $table->dropForeign(['container_id']);
            $table->dropForeign(['asset_usage_id_snapshot']);
            $table->dropColumn([
                'asset_id',
                'asset_number_snapshot',
                'serial_number_snapshot',
                'barcode_snapshot',
                'lifecycle_status_snapshot',
                'container_id',
                'container_name_snapshot',
                'container_barcode_snapshot',
                'repair_status_snapshot',
                'inspection_status_snapshot',
                'asset_usage_id_snapshot',
            ]);
        });
    }
};
