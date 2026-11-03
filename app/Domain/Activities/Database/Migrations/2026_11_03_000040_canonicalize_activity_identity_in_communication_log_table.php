<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replace('App\\Models\\Activity', 'App\\Domain\\Activities\\Models\\Activity');
    }

    public function down(): void
    {
        $this->replace('App\\Domain\\Activities\\Models\\Activity', 'App\\Models\\Activity');
    }

    private function replace(string $from, string $to): void
    {
        DB::table('communication_log')->where('notifiable_type', $from)->update(['notifiable_type' => $to]);
        DB::table('communication_log')->where('subject_type', $from)->update(['subject_type' => $to]);
    }
};
