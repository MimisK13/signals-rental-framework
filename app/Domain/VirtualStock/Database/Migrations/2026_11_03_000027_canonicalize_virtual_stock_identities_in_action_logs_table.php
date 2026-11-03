<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const IDENTITIES = [
        'App\\Models\\VirtualStockIntake' => 'App\\Domain\\VirtualStock\\Models\\VirtualStockIntake',
        'App\\Models\\VirtualStockCostOverride' => 'App\\Domain\\VirtualStock\\Models\\VirtualStockCostOverride',
    ];

    public function up(): void
    {
        $this->rewrite(self::IDENTITIES);
    }

    public function down(): void
    {
        $this->rewrite(array_flip(self::IDENTITIES));
    }

    /** @param array<string, string> $identities */
    private function rewrite(array $identities): void
    {
        foreach ($identities as $from => $to) {
            DB::table('action_logs')->where('auditable_type', $from)->update(['auditable_type' => $to]);
        }
    }
};
