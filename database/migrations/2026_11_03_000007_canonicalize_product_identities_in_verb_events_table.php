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
        $this->rewrite(self::IDENTITIES);
    }

    public function down(): void
    {
        $this->rewrite(array_flip(self::IDENTITIES));
    }

    /** @param array<string, string> $identities */
    private function rewrite(array $identities): void
    {
        $connection = DB::connection(config('verbs.connections.events'));
        $table = (string) config('verbs.tables.events', 'verb_events');

        $connection->table($table)->select(['id', 'data'])->orderBy('id')->chunkById(200, function ($events) use ($connection, $table, $identities): void {
            foreach ($events as $event) {
                $data = is_string($event->data) ? json_decode($event->data, true, flags: JSON_THROW_ON_ERROR) : $event->data;
                $rewritten = $this->replaceRecursively($data, $identities);

                if ($rewritten !== $data) {
                    $connection->table($table)->where('id', $event->id)->update(['data' => json_encode($rewritten, JSON_THROW_ON_ERROR)]);
                }
            }
        });
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
