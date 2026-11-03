<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replace('App\\Models\\User', 'App\\Domain\\Administration\\Models\\User');
    }

    public function down(): void
    {
        $this->replace('App\\Domain\\Administration\\Models\\User', 'App\\Models\\User');
    }

    private function replace(string $from, string $to): void
    {
        DB::table('communication_log')->where('notifiable_type', $from)->update(['notifiable_type' => $to]);
        DB::table('communication_log')->where('subject_type', $from)->update(['subject_type' => $to]);
    }
};
