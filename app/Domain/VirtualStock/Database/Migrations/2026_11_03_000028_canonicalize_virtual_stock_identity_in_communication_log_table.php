<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replace('App\\Models\\VirtualStockIntake', 'App\\Domain\\VirtualStock\\Models\\VirtualStockIntake');
    }

    public function down(): void
    {
        $this->replace('App\\Domain\\VirtualStock\\Models\\VirtualStockIntake', 'App\\Models\\VirtualStockIntake');
    }

    private function replace(string $from, string $to): void
    {
        DB::table('communication_log')->where('subject_type', $from)->update(['subject_type' => $to]);
    }
};
