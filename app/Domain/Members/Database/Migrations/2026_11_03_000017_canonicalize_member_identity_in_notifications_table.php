<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replace('App\\Models\\Member', 'App\\Domain\\Members\\Models\\Member');
    }

    public function down(): void
    {
        $this->replace('App\\Domain\\Members\\Models\\Member', 'App\\Models\\Member');
    }

    private function replace(string $from, string $to): void
    {
        DB::table('notifications')->where('notifiable_type', $from)->update(['notifiable_type' => $to]);
    }
};
