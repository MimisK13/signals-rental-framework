<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemReservation;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DispatchBulkQuantity;
use App\Actions\Opportunities\ReturnBulkQuantity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\BulkDispatchData;
use App\Data\Opportunities\BulkReturnData;
use App\Data\Opportunities\ChangeItemReservationData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\ContainerCheckinMode;
use App\Enums\ContainerStatus;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Models\ActionLog;
use App\Models\Container;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class)->group('pgsql');

/**
 * @return array<int, array<string, mixed>>
 */
function runC73Workers(int $containerId): array
{
    $connection = DB::connection('pgsql_testing');
    while ($connection->transactionLevel() > 0) {
        $connection->commit();
    }

    $barrier = storage_path('framework/c73-verify-barrier-'.bin2hex(random_bytes(8)));
    $worker = base_path('tests/Pgsql/Support/ReservationConcurrencyWorker.php');
    $processes = collect([1, 2])->map(static fn (): Process => new Process([
        PHP_BINARY,
        $worker,
        'verify_container',
        (string) $containerId,
        '0',
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

        expect($ready)->toBe([true, true], collect($processes)
            ->map(fn (Process $process): string => $process->getErrorOutput().$process->getOutput())
            ->implode("\n---\n"));
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

/**
 * @return array<int, array<string, mixed>>
 */
function runC73BulkWorkers(int $itemId): array
{
    $connection = DB::connection('pgsql_testing');
    while ($connection->transactionLevel() > 0) {
        $connection->commit();
    }

    $barrier = storage_path('framework/c73-bulk-barrier-'.bin2hex(random_bytes(8)));
    $worker = base_path('tests/Pgsql/Support/ReservationConcurrencyWorker.php');
    $processes = collect([1, 2])->map(static fn (): Process => new Process([
        PHP_BINARY,
        $worker,
        'return_bulk',
        (string) $itemId,
        '30',
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

        expect($ready)->toBe([true, true], collect($processes)
            ->map(fn (Process $process): string => $process->getErrorOutput().$process->getOutput())
            ->implode("\n---\n"));
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

it('serializes overlapping container verification through the container row lock', function (): void {
    User::factory()->owner()->create();
    $product = Product::factory()->containerable()->create([
        'container_checkin_mode' => ContainerCheckinMode::ParentThenVerify->value,
    ]);
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $container = Container::factory()->temporary()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'status' => ContainerStatus::Returned->value,
        'checkin_verification_required_at' => now()->subMinute(),
    ]);

    $results = runC73Workers($container->id);

    expect(collect($results)->where('ok', true))->toHaveCount(1, json_encode($results, JSON_THROW_ON_ERROR))
        ->and(collect($results)->where('ok', false))->toHaveCount(1)
        ->and($container->refresh()->checkin_verification_required_at)->toBeNull()
        ->and(ActionLog::query()
            ->where('action', 'container.checkin_verified')
            ->where('auditable_id', $container->id)
            ->count())->toBe(1);
});

it('prevents overlapping bulk returns from exceeding the dispatched quantity', function (): void {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 100,
    ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'C7.3 bulk return race',
        'store_id' => $store->id,
        'starts_at' => '2026-11-01T09:00:00Z',
        'ends_at' => '2026-11-03T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->findOrFail($created->id);
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => OpportunityItemType::Product->value,
        'quantity' => '100',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    $item = $opportunity->refresh()->items()->firstOrFail();
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '0']));
    (new ConvertToQuotation)($opportunity->refresh());
    (new ConvertToOrder)($opportunity->refresh());
    (new DispatchBulkQuantity)(
        $item->refresh(),
        BulkDispatchData::from(['quantity' => '100']),
    );
    (new ReturnBulkQuantity)(
        $item->refresh(),
        BulkReturnData::from(['quantity' => '60']),
    );

    $results = runC73BulkWorkers($item->id);

    expect(collect($results)->where('ok', true))->toHaveCount(1)
        ->and(collect($results)->where('ok', false))->toHaveCount(1)
        ->and((float) OpportunityItem::query()->findOrFail($item->id)->returned_quantity)->toBe(90.0);
});
