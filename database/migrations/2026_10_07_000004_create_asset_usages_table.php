<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->foreignId('opportunity_item_id')->constrained('opportunity_items')->restrictOnDelete();
            $table->dateTime('dispatched_at');
            $table->dateTime('returned_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['asset_id', 'opportunity_item_id', 'dispatched_at'],
                'asset_usages_identity_unique',
            );
            $table->index('opportunity_item_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX asset_usages_active_asset_unique '
            .'ON asset_usages (asset_id) WHERE returned_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS asset_usages_active_asset_unique');

        Schema::dropIfExists('asset_usages');
    }
};
