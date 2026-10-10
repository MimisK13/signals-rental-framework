<?php

namespace App\Data\Containers;

use App\Enums\ContainerScanMode;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class CreateDurableContainerData extends Data
{
    public function __construct(
        public int $product_id,
        public int $store_id,
        public string $asset_number,
        public ?string $serial_number = null,
        public ?string $barcode = null,
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'asset_number' => ['required', 'string', 'max:255'],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'scan_mode' => ['sometimes', 'nullable', 'string', Rule::enum(ContainerScanMode::class)],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
