<?php

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class)->group('pgsql');

/**
 * @return list<array{ok: bool, exception?: string, message?: string}>
 */
function runD1StockTransactionWorkers(int $stockLevelId): array
{
    $connection = DB::connection('pgsql_testing');
    while ($connection->transactionLevel() > 0) {
        $connection->commit();
    }

    $barrier = storage_path('framework/d1-stock-transaction-barrier-'.bin2hex(random_bytes(8)));
    $worker = base_path('tests/Pgsql/Support/ReservationConcurrencyWorker.php');
    $processes = collect([1, 2])->map(static fn (): Process => new Process([
        PHP_BINARY,
        $worker,
        'stock_transaction_reduce',
        (string) $stockLevelId,
        '7',
        $barrier,
    ], base_path()))->all();

    try {
        foreach ($processes as $process) {
            $process->start();
        }

        $ready = array_fill(0, count($processes), false);
        for ($attempt = 0; $attempt < 500 && in_array(false, $ready, true); $attempt++) {
            foreach ($processes as $index => $process) {
                if (str_contains($process->getIncrementalOutput(), "ready\n")) {
                    $ready[$index] = true;
                }
            }

            if (in_array(false, $ready, true)) {
                usleep(10000);
            }
        }

        expect($ready)->toBe([true, true]);
        file_put_contents($barrier, 'go');

        foreach ($processes as $process) {
            $process->wait();
        }

        return collect($processes)->map(static function (Process $process): array {
            $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));

            return json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
        })->all();
    } finally {
        if (file_exists($barrier)) {
            unlink($barrier);
        }

        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }

        $connection->beginTransaction();
    }
}

it('serializes overlapping physical quantity mutations on PostgreSQL', function (): void {
    User::factory()->owner()->create();
    $product = Product::factory()->bulk()->create();
    $store = Store::factory()->create();
    $stockLevel = StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 10,
    ]);

    $results = runD1StockTransactionWorkers($stockLevel->id);

    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(collect($results)->where('ok', false))->toHaveCount(1)
        ->and((float) $stockLevel->refresh()->quantity_held)->toBe(3.0)
        ->and(StockTransaction::query()
            ->where('stock_level_id', $stockLevel->id)
            ->where('quantity_before', '10.00')
            ->where('quantity_after', '3.00')
            ->count())->toBe(1);
});
