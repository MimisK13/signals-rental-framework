<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replace('App\\Models\\Opportunity', 'App\\Domain\\Projects\\Opportunities\\Models\\Opportunity');
    }

    public function down(): void
    {
        $this->replace('App\\Domain\\Projects\\Opportunities\\Models\\Opportunity', 'App\\Models\\Opportunity');
    }

    private function replace(string $from, string $to): void
    {
        DB::table('custom_field_values')->where('entity_type', $from)->update(['entity_type' => $to]);
    }
};
