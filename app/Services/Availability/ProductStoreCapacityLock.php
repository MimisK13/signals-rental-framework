<?php

namespace App\Services\Availability;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Transaction-scoped capacity lock shared by product/store mutations.
 */
class ProductStoreCapacityLock
{
    public function acquire(int $productId, int $storeId): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        if ($connection->transactionLevel() === 0) {
            throw new RuntimeException('A product/store capacity lock must be acquired inside a database transaction.');
        }

        $connection->selectOne(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
            [sprintf('%d:%d', $productId, $storeId)],
        );
    }

    /**
     * @param  list<array{product_id: int, store_id: int}>  $scopes
     */
    public function acquireMany(array $scopes): void
    {
        usort($scopes, static function (array $left, array $right): int {
            return [$left['product_id'], $left['store_id']] <=> [$right['product_id'], $right['store_id']];
        });

        $previous = null;
        foreach ($scopes as $scope) {
            $key = $scope['product_id'].':'.$scope['store_id'];
            if ($key === $previous) {
                continue;
            }

            $this->acquire($scope['product_id'], $scope['store_id']);
            $previous = $key;
        }
    }
}
