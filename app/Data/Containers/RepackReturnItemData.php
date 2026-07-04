<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

/**
 * Input DTO for returning and optionally repacking one item during a repack session.
 */
class RepackReturnItemData extends Data
{
    public function __construct(
        public int $opportunity_id,
        public int $serialised_item_id,
        public bool $free_scan = false,
        public ?int $received_by = null,
        public ?int $return_store_id = null,
        public ?string $returned_at = null,
        public ?string $position = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'opportunity_id' => ['required', 'integer', 'exists:opportunities,id'],
            'serialised_item_id' => ['required', 'integer', 'exists:stock_levels,id'],
            'free_scan' => ['sometimes', 'boolean'],
            'received_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'return_store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'returned_at' => ['sometimes', 'nullable', 'date'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
