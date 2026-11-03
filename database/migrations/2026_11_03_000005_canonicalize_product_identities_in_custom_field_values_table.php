<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replace('App\\Models\\Product', 'App\\Domain\\Products\\Models\\Product');
        $this->replace('App\\Models\\ProductGroup', 'App\\Domain\\Products\\Models\\ProductGroup');
    }

    public function down(): void
    {
        $this->replace('App\\Domain\\Products\\Models\\Product', 'App\\Models\\Product');
        $this->replace('App\\Domain\\Products\\Models\\ProductGroup', 'App\\Models\\ProductGroup');
    }

    private function replace(string $from, string $to): void
    {
        DB::table('custom_field_values')->where('entity_type', $from)->update(['entity_type' => $to]);
    }
};
