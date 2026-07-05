<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parent-then-verify check-in mode flags a returned kit for per-item condition
 * assessment (serialised-containers.md §"Check-In Scanning Modes").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table): void {
            $table->timestampTz('checkin_verification_required_at')
                ->nullable()
                ->after('returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table): void {
            $table->dropColumn('checkin_verification_required_at');
        });
    }
};
