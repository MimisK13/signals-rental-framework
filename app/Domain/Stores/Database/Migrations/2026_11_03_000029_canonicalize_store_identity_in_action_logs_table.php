<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replace('App\\Models\\Store', 'App\\Domain\\Stores\\Models\\Store');
    }

    public function down(): void
    {
        $this->replace('App\\Domain\\Stores\\Models\\Store', 'App\\Models\\Store');
    }

    private function replace(string $from, string $to): void
    {
        DB::table('action_logs')->where('auditable_type', $from)->update(['auditable_type' => $to]);
    }
};
