<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const IDENTITIES = [
        'App\\Models\\Activity' => 'App\\Domain\\Activities\\Models\\Activity',
        'App\\Models\\ActivityParticipant' => 'App\\Domain\\Activities\\Models\\ActivityParticipant',
    ];

    public function up(): void
    {
        $this->replace(self::IDENTITIES);
    }

    public function down(): void
    {
        $this->replace(array_flip(self::IDENTITIES));
    }

    /** @param array<string, string> $identities */
    private function replace(array $identities): void
    {
        foreach ($identities as $from => $to) {
            DB::table('action_logs')->where('auditable_type', $from)->update(['auditable_type' => $to]);
        }
    }
};
