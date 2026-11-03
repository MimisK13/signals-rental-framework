<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_PREFIX = 'App\\Verbs\\States\\Opportunity';

    private const CANONICAL_PREFIX = 'App\\Domain\\Projects\\Opportunities\\Verbs\\States\\Opportunity';

    public function up(): void
    {
        $this->replace(self::OLD_PREFIX, self::CANONICAL_PREFIX);
    }

    public function down(): void
    {
        $this->replace(self::CANONICAL_PREFIX, self::OLD_PREFIX);
    }

    private function replace(string $from, string $to): void
    {
        $connection = DB::connection(config('verbs.connections.state_events'));
        $table = (string) config('verbs.tables.state_events', 'verb_state_events');

        $connection->table($table)
            ->where('state_type', 'like', $from.'%')
            ->orderBy('id')
            ->chunkById(200, function ($stateEvents) use ($connection, $table, $from, $to): void {
                foreach ($stateEvents as $stateEvent) {
                    $connection->table($table)->where('id', $stateEvent->id)->update([
                        'state_type' => str_replace($from, $to, (string) $stateEvent->state_type),
                    ]);
                }
            });
    }
};
