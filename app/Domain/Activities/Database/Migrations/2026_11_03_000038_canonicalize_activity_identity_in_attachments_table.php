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
        DB::table('attachments')->where('attachable_type', $from)->update(['attachable_type' => $to]);
    }
};
