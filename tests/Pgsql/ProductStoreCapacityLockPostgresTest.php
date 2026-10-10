<?php

use App\Services\Availability\ProductStoreCapacityLock;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class)->group('pgsql');

it('holds the product/store lock across independent PostgreSQL transactions', function (): void {
    $primary = DB::connection('pgsql_testing');
    $secondary = DB::connectUsing('capacity_lock_secondary', config('database.connections.pgsql_testing'));

    $secondary->beginTransaction();

    try {
        app(ProductStoreCapacityLock::class)->acquire(7001, 8001);

        $blocked = $secondary->selectOne(
            'SELECT pg_try_advisory_xact_lock(hashtextextended(?, 0)) AS acquired',
            ['7001:8001'],
        );

        expect((bool) $blocked->acquired)->toBeFalse();

        $primary->commit();

        $available = $secondary->selectOne(
            'SELECT pg_try_advisory_xact_lock(hashtextextended(?, 0)) AS acquired',
            ['7001:8001'],
        );

        expect((bool) $available->acquired)->toBeTrue();
    } finally {
        if ($secondary->transactionLevel() > 0) {
            $secondary->rollBack();
        }

        DB::purge('capacity_lock_secondary');

        if ($primary->transactionLevel() === 0) {
            $primary->beginTransaction();
        }
    }
});
