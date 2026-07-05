<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

/**
 * Approved dispatch-time amendment for a product missing from (or short on) the
 * opportunity (serialised-containers.md §"Dispatch Endpoint Detail").
 */
class ContainerDispatchAmendmentData extends Data
{
    public function __construct(
        public int $product_id,
        /** `add` creates a new line; `increase` raises an existing line's quantity. */
        public string $action,
        public string $quantity = '1',
        public ?int $price = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'action' => ['required', 'string', 'in:add,increase'],
            'quantity' => ['sometimes', 'numeric', 'min:0.01'],
            'price' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
