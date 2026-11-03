<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const IDENTITIES = [
        'App\\Verbs\\Events\\VirtualStock\\' => 'App\\Domain\\VirtualStock\\Verbs\\Events\\',
        'App\\Verbs\\States\\VirtualStockIntakeState' => 'App\\Domain\\VirtualStock\\Verbs\\States\\VirtualStockIntakeState',
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
        $connection = DB::connection(config('verbs.connections.snapshots'));
        $table = (string) config('verbs.tables.snapshots', 'verb_snapshots');

        $connection->table($table)->select(['id', 'type', 'data'])->orderBy('id')->chunkById(200, function ($snapshots) use ($connection, $table, $identities): void {
            foreach ($snapshots as $snapshot) {
                $data = is_string($snapshot->data) ? json_decode($snapshot->data, true, flags: JSON_THROW_ON_ERROR) : $snapshot->data;
                $rewrittenData = $this->replaceRecursively($data, $identities);
                $rewrittenType = str_replace(array_keys($identities), array_values($identities), (string) $snapshot->type);

                if ($rewrittenType !== $snapshot->type || $rewrittenData !== $data) {
                    $this->update($connection, $table, $snapshot->id, $rewrittenType, $rewrittenData);
                }
            }
        });
    }

    private function update(ConnectionInterface $connection, string $table, mixed $id, string $type, mixed $data): void
    {
        $connection->table($table)->where('id', $id)->update([
            'type' => $type,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
        ]);
    }

    /** @param array<string, string> $identities */
    private function replaceRecursively(mixed $value, array $identities): mixed
    {
        if (is_string($value)) {
            return str_replace(array_keys($identities), array_values($identities), $value);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->replaceRecursively($item, $identities);
        }

        return $value;
    }
};
