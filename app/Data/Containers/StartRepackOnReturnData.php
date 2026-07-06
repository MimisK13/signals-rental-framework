<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

/**
 * Input DTO for initiating repack-on-return during opportunity check-in.
 */
class StartRepackOnReturnData extends Data
{
    public function __construct(
        public int $opportunity_id,
        public int $serialised_item_id,
        public ?int $received_by = null,
        public ?int $return_store_id = null,
        public ?string $returned_at = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'opportunity_id' => ['required', 'integer', 'exists:opportunities,id'],
            'serialised_item_id' => ['required', 'integer', 'exists:stock_levels,id'],
            'received_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'return_store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'returned_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
