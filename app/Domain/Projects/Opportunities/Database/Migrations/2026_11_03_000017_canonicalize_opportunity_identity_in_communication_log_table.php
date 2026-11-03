<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FROM = 'App\\Models\\Opportunity';

    private const TO = 'App\\Domain\\Projects\\Opportunities\\Models\\Opportunity';

    public function up(): void
    {
        $this->rewrite(self::FROM, self::TO);
    }

    public function down(): void
    {
        $this->rewrite(self::TO, self::FROM);
    }

    private function rewrite(string $from, string $to): void
    {
        DB::table('communication_log')->where('notifiable_type', $from)->update(['notifiable_type' => $to]);
        DB::table('communication_log')->where('subject_type', $from)->update(['subject_type' => $to]);
    }
};
