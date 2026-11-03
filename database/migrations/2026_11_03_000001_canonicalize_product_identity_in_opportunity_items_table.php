<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('opportunity_items')
            ->where('itemable_type', 'App\\Models\\Product')
            ->update(['itemable_type' => 'App\\Domain\\Products\\Models\\Product']);
    }

    public function down(): void
    {
        DB::table('opportunity_items')
            ->where('itemable_type', 'App\\Domain\\Products\\Models\\Product')
            ->update(['itemable_type' => 'App\\Models\\Product']);
    }
};
