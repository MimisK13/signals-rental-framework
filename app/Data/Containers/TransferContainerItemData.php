<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

/**
 * Input DTO for atomically moving an item between containers.
 */
class TransferContainerItemData extends Data
{
    public function __construct(
        public int $serialised_item_id,
        public int $source_container_id,
        public ?string $position = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'serialised_item_id' => ['required', 'integer', 'exists:stock_levels,id'],
            'source_container_id' => ['required', 'integer', 'exists:containers,id'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
