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
        DB::table('model_has_roles')->where('model_type', $from)->update(['model_type' => $to]);
    }
};
