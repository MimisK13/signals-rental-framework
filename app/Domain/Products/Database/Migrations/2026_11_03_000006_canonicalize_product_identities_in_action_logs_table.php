<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const IDENTITIES = [
        'App\\Models\\Product' => 'App\\Domain\\Products\\Models\\Product',
        'App\\Models\\ProductGroup' => 'App\\Domain\\Products\\Models\\ProductGroup',
        'App\\Models\\Accessory' => 'App\\Domain\\Products\\Models\\Accessory',
        'App\\Models\\SerialisedComponent' => 'App\\Domain\\Products\\Models\\SerialisedComponent',
    ];

    public function up(): void
    {
        foreach (self::IDENTITIES as $from => $to) {
            $this->replace($from, $to);
        }
    }

    public function down(): void
    {
        foreach (self::IDENTITIES as $from => $to) {
            $this->replace($to, $from);
        }
    }

    private function replace(string $from, string $to): void
    {
        DB::table('action_logs')->where('auditable_type', $from)->update(['auditable_type' => $to]);
    }
};
