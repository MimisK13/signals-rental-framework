<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Data;

use App\Domain\Warehouse\Containers\Enums\PackContainerResolution;
use Spatie\LaravelData\Data;

/**
 * Input DTO for packing a single serialised item into an open container.
 */
final class PackContainerItemData extends Data
{
    public function __construct(
        public int $serialised_item_id,
        public ?string $position = null,
        public ?string $notes = null,
        /** @see PackContainerResolution */
        public ?string $resolve = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'serialised_item_id' => ['required', 'integer', 'exists:stock_levels,id'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'resolve' => ['sometimes', 'nullable', 'string', 'in:transfer,auto_return_and_pack,confirm_nesting'],
        ];
    }
}
