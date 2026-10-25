<?php

namespace App\Data\Opportunities;

use App\Models\Product;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Passive Product rows prepared for one line-items read flow.
 */
final readonly class PreparedProductInputs
{
    /**
     * @param  array<int, Product>  $productsById
     */
    public function __construct(private array $productsById) {}

    /**
     * @param  Collection<int, Product>  $products
     */
    public static function fromCollection(Collection $products): self
    {
        /** @var array<int, Product> $productsById */
        $productsById = $products->keyBy('id')->all();

        return new self($productsById);
    }

    public function has(int $productId): bool
    {
        return array_key_exists($productId, $this->productsById);
    }

    public function get(int $productId): ?Product
    {
        return $this->productsById[$productId] ?? null;
    }

    public function required(int $productId): Product
    {
        $product = $this->get($productId);

        if ($product === null) {
            throw new LogicException("Prepared Product {$productId} is missing from the line-items read inputs.");
        }

        return $product;
    }
}
