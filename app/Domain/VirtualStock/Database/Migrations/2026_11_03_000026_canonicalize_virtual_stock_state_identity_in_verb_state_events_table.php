<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD = 'App\\Verbs\\States\\VirtualStockIntakeState';

    private const CANONICAL = 'App\\Domain\\VirtualStock\\Verbs\\States\\VirtualStockIntakeState';

    public function up(): void
    {
        $this->replace(self::OLD, self::CANONICAL);
    }

    public function down(): void
    {
        $this->replace(self::CANONICAL, self::OLD);
    }

    private function replace(string $from, string $to): void
    {
        $connection = DB::connection(config('verbs.connections.state_events'));
        $table = (string) config('verbs.tables.state_events', 'verb_state_events');

        $connection->table($table)->where('state_type', $from)->update(['state_type' => $to]);
    }
};
