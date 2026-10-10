<?php

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

        Schema::table('stocktake_observations', function (Blueprint $table): void {
            $table->dropForeign(['stocktake_line_id']);
            $table->foreignId('stocktake_line_id')->nullable()->change();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('stock_level_id')->nullable()->constrained('stock_levels')->nullOnDelete();
            $table->string('identifier')->nullable();
            $table->string('observation_type', 30)->default('bulk_count');
            $table->decimal('counted_quantity', 10, 2)->nullable()->change();
            $table->string('asset_number_snapshot')->nullable();
            $table->string('serial_number_snapshot')->nullable();
            $table->string('barcode_snapshot')->nullable();
            $table->string('lifecycle_status_snapshot')->nullable();
            $table->foreignId('store_id_snapshot')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('container_id_snapshot')->nullable()->constrained('containers')->nullOnDelete();
            $table->string('container_name_snapshot')->nullable();
            $table->string('container_barcode_snapshot')->nullable();
            $table->string('repair_status_snapshot')->nullable();
            $table->string('inspection_status_snapshot')->nullable();
            $table->foreignId('asset_usage_id_snapshot')->nullable()->constrained('asset_usages')->nullOnDelete();

            $table->foreign('stocktake_line_id')
                ->references('id')
                ->on('stocktake_lines')
                ->restrictOnDelete();

            $table->unique(['stocktake_id', 'asset_id']);
            $table->index(['stocktake_id', 'observation_type']);
            $table->index('identifier');
        });
    }

    public function down(): void
    {
        Schema::table('stocktake_observations', function (Blueprint $table): void {
            $table->dropUnique(['stocktake_id', 'asset_id']);
            $table->dropIndex(['stocktake_id', 'observation_type']);
            $table->dropIndex('stocktake_observations_identifier_index');
            $table->dropForeign(['stocktake_line_id']);
            $table->dropForeign(['asset_id']);
            $table->dropForeign(['stock_level_id']);
            $table->dropForeign(['store_id_snapshot']);
            $table->dropForeign(['container_id_snapshot']);
            $table->dropForeign(['asset_usage_id_snapshot']);
            $table->dropColumn([
                'asset_id',
                'stock_level_id',
                'identifier',
                'observation_type',
                'asset_number_snapshot',
                'serial_number_snapshot',
                'barcode_snapshot',
                'lifecycle_status_snapshot',
                'store_id_snapshot',
                'container_id_snapshot',
                'container_name_snapshot',
                'container_barcode_snapshot',
                'repair_status_snapshot',
                'inspection_status_snapshot',
                'asset_usage_id_snapshot',
            ]);
            $table->decimal('counted_quantity', 10, 2)->nullable(false)->change();
            $table->foreignId('stocktake_line_id')->nullable(false)->change();
            $table->foreign('stocktake_line_id')
                ->references('id')
                ->on('stocktake_lines')
                ->restrictOnDelete();
        });

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
