<?php

use App\Models\DocumentSequence;
use App\Models\Store;
use App\Services\Documents\DocumentNumberGenerator;
use Illuminate\Console\Application;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Laravel\SerializableClosure\SerializableClosure;
use Tests\Concerns\UsesPostgres;

/*
| PostgreSQL lane: proves document sequence allocation is concurrency-safe via
| SELECT FOR UPDATE on real Postgres.
|
|   php artisan test --compact --group=pgsql tests/Pgsql/DocumentNumberGeneratorPostgresTest.php
*/

uses(UsesPostgres::class)->group('pgsql');

/**
 * @return list<string>
 */
function runConcurrentDocumentAllocations(int $storeId, string $documentType, int $count = 10): array
{
    $closure = fn (): string => app(DocumentNumberGenerator::class)->next($storeId, $documentType);
    $command = Application::formatCommandString('invoke-serialized-closure');

    $results = Process::pool(function (Pool $pool) use ($closure, $command, $count): void {
        for ($i = 0; $i < $count; $i++) {
            $pool->as((string) $i)->path(base_path())->env([
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql_testing',
                'LARAVEL_INVOKABLE_CLOSURE' => base64_encode(
                    serialize(new SerializableClosure($closure)),
                ),
            ])->command($command);
        }
    })->start()->wait();

    return $results->collect()->map(function ($result): string {
        if ($result->failed()) {
            throw new RuntimeException(
                'Concurrent process failed with exit code ['.$result->exitCode().']. Message: '.$result->errorOutput(),
            );
        }

        $output = $result->output();

        if (($pos = strpos($output, "\x1f\x8b")) !== false) {
            $output = substr($output, 0, $pos);
        }

        $payload = json_decode($output, true);

        if (! is_array($payload) || ! ($payload['successful'] ?? false)) {
            $message = is_array($payload) ? ($payload['message'] ?? 'Unknown concurrent worker failure') : 'Invalid worker output';

            throw new RuntimeException($message);
        }

        return unserialize($payload['result']);
    })->values()->all();
}

describe('DocumentNumberGenerator concurrency (pgsql)', function () {
    it('issues unique numbers under concurrent allocation', function () {
        $store = Store::factory()->create();
        DocumentSequence::factory()->create([
            'store_id' => $store->id,
            'document_type' => 'invoice',
            'prefix' => 'INV-',
            'include_year' => true,
            'include_month' => false,
            'padding' => 4,
            'next_number' => 1,
            'reset_frequency' => 'never',
        ]);

        // Child worker processes use their own connections and cannot see rows
        // that only exist inside this test's open transaction.
        DB::connection('pgsql_testing')->commit();

        $numbers = runConcurrentDocumentAllocations($store->id, 'invoice');

        expect($numbers)->toHaveCount(10)
            ->and(array_unique($numbers))->toHaveCount(10);

        $sequence = DocumentSequence::query()
            ->where('store_id', $store->id)
            ->where('document_type', 'invoice')
            ->first();

        expect($sequence?->next_number)->toBe(11);

        DB::connection('pgsql_testing')->beginTransaction();
    });
});
