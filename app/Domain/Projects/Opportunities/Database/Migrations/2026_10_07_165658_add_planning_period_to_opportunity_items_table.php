<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dateTime('planning_starts_at')->nullable()->after('ends_at');
            $table->dateTime('planning_ends_at')->nullable()->after('planning_starts_at');
            $table->index(['planning_starts_at', 'planning_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropIndex(['planning_starts_at', 'planning_ends_at']);
            $table->dropColumn(['planning_starts_at', 'planning_ends_at']);
        });
    }
};
