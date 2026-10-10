<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dateTime('planning_starts_at')->nullable()->after('ends_at');
            $table->dateTime('planning_ends_at')->nullable()->after('planning_starts_at');
            $table->unsignedInteger('reservation_priority')->default(0)->after('rating');
            $table->index(['planning_starts_at', 'planning_ends_at']);
        });

        Schema::table('opportunity_items', function (Blueprint $table) {
            $table->dateTime('planning_starts_at')->nullable()->after('ends_at');
            $table->dateTime('planning_ends_at')->nullable()->after('planning_starts_at');
            $table->index(['planning_starts_at', 'planning_ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table) {
            $table->dropIndex(['planning_starts_at', 'planning_ends_at']);
            $table->dropColumn(['planning_starts_at', 'planning_ends_at']);
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['planning_starts_at', 'planning_ends_at']);
            $table->dropColumn(['planning_starts_at', 'planning_ends_at', 'reservation_priority']);
        });
    }
};
