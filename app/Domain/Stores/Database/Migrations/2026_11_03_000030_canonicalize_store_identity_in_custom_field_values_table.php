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
        DB::table('custom_field_values')->where('entity_type', $from)->update(['entity_type' => $to]);
    }
};
