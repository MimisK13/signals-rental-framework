<?php

namespace App\Data\Opportunities;

use LogicException;

final readonly class PreparedStockInputs
{
    /**
     * @param  array<string, int>  $stockByKey
     */
    public function __construct(private array $stockByKey) {}

    public static function key(int $productId, int $storeId): string
    {
        return $productId.'|'.$storeId;
    }

    public function has(int $productId, int $storeId): bool
    {
        return array_key_exists(self::key($productId, $storeId), $this->stockByKey);
    }

    public function required(int $productId, int $storeId): int
    {
        $key = self::key($productId, $storeId);

        if (! array_key_exists($key, $this->stockByKey)) {
            throw new LogicException("Prepared stock {$key} is missing from the line-items read inputs.");
        }

        return $this->stockByKey[$key];
    }
}
