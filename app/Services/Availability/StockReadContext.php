<?php

namespace App\Services\Availability;

use Closure;

/**
 * Explicit raw-stock context for one read-only availability workflow.
 *
 * This is deliberately not a service cache: the owner creates it for one
 * shortage evaluation and passes it through the participating readers.
 */
final class StockReadContext
{
    /** @var array<string, int> */
    private array $facts = [];

    public function resolve(int $productId, int $storeId, Closure $loader): int
    {
        $key = $this->key($productId, $storeId);

        if (array_key_exists($key, $this->facts)) {
            return $this->facts[$key];
        }

        return $this->facts[$key] = $loader();
    }

    private function key(int $productId, int $storeId): string
    {
        return $productId.'|'.$storeId;
    }
}
