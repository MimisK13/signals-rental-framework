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
            $table->foreign('accepted_observation_id')
                ->references('id')
                ->on('stocktake_observations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stocktake_lines', function (Blueprint $table): void {
            $table->dropForeign(['accepted_observation_id']);
        });
    }
};
