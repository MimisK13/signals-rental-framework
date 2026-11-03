<?php

declare(strict_types=1);

use App\Domain\Members\Enums\MembershipType;
use App\Domain\Members\Models\Member;
use App\Domain\Products\Models\Product;
use App\Domain\SupplierCosts\Models\ProductSupplierCost;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class)->group('pgsql');

/**
 * @param  list<int>  $targets
 * @return list<array{ok: bool, exception?: string, message?: string}>
 */
function runSupplierCostConcurrencyWorkers(
    string $operation,
    array $targets,
    int $productId,
    int $supplierId,
    int $userId,
): array {
    $connection = DB::connection('pgsql_testing');
    while ($connection->transactionLevel() > 0) {
        $connection->commit();
    }

    $barrier = storage_path('framework/supplier-cost-concurrency-'.bin2hex(random_bytes(8)));
    $processes = array_map(static function (int $target) use ($operation, $productId, $supplierId, $userId, $barrier): Process {
        return new Process([
            PHP_BINARY,
            base_path('tests/Pgsql/Support/SupplierCostConcurrencyWorker.php'),
            $operation,
            (string) $target,
            (string) $productId,
            (string) $supplierId,
            (string) $userId,
            $barrier,
        ], base_path(), timeout: 30);
    }, $targets);

    try {
        foreach ($processes as $process) {
            $process->start();
        }

        $ready = array_fill(0, count($processes), false);
        $deadline = microtime(true) + 15;
        while (in_array(false, $ready, true) && microtime(true) < $deadline) {
            foreach ($processes as $index => $process) {
                if (str_contains($process->getIncrementalOutput(), "ready\n")) {
                    $ready[$index] = true;
                }
                if (! $process->isRunning() && ! $ready[$index]) {
                    throw new RuntimeException('Supplier cost worker failed before the barrier: '.$process->getErrorOutput().$process->getOutput());
                }
            }
            if (in_array(false, $ready, true)) {
                usleep(10000);
            }
        }

        expect($ready)->toBe([true, true]);
        file_put_contents($barrier, 'go');

        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
            $results[] = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
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

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->product = Product::factory()->create();
    $this->supplier = Member::factory()->create([
        'membership_type' => MembershipType::Organisation,
    ]);
});

it('serializes concurrent null-store duplicate creation', function (): void {
    $results = runSupplierCostConcurrencyWorkers(
        'create',
        [0, 0],
        $this->product->id,
        $this->supplier->id,
        $this->user->id,
    );

    expect(collect($results)->where('ok', true))->toHaveCount(1);
    expect(collect($results)->where('exception', ValidationException::class))->toHaveCount(1);
    expect(ProductSupplierCost::query()->where('product_id', $this->product->id)->count())->toBe(1);
});

it('serializes concurrent preferred supplier selection', function (): void {
    $first = ProductSupplierCost::factory()->create(['product_id' => $this->product->id]);
    $second = ProductSupplierCost::factory()->create(['product_id' => $this->product->id]);

    $results = runSupplierCostConcurrencyWorkers(
        'prefer',
        [$first->id, $second->id],
        $this->product->id,
        $this->supplier->id,
        $this->user->id,
    );

    expect(collect($results)->where('ok', true))->toHaveCount(2);
    expect(ProductSupplierCost::query()
        ->where('product_id', $this->product->id)
        ->where('is_preferred', true)
        ->count())->toBe(1);
});
