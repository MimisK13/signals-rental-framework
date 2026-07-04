<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * Input DTO for dispatching a sealed transport container against an opportunity.
 */
class DispatchContainerData extends Data
{
    /**
     * @param  DataCollection<int, ContainerDispatchAmendmentData>|null  $amendments
     */
    public function __construct(
        public int $opportunity_id,
        /** When true, warehouse-addable products are auto-added without an explicit amendment row. */
        public bool $auto_add_missing = true,
        /** User-approved amendments for products that returned a prompt verdict. */
        #[DataCollectionOf(ContainerDispatchAmendmentData::class)]
        public ?DataCollection $amendments = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'opportunity_id' => ['required', 'integer', 'exists:opportunities,id'],
            'auto_add_missing' => ['sometimes', 'boolean'],
            'amendments' => ['sometimes', 'nullable', 'array'],
            'amendments.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'amendments.*.action' => ['required', 'string', 'in:add,increase'],
            'amendments.*.quantity' => ['sometimes', 'numeric', 'min:0.01'],
            'amendments.*.price' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
