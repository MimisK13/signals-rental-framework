<?php

namespace App\Data\Containers;

use App\Enums\ContainerScanMode;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class CreateContainerData extends Data
{
    public function __construct(
        public int $serialised_item_id,
        public ?string $name = null,
        public ?string $scan_mode = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'serialised_item_id' => ['required', 'integer', 'exists:stock_levels,id'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'scan_mode' => ['sometimes', 'nullable', 'string', Rule::enum(ContainerScanMode::class)],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
