<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

class CreateTemporaryContainerData extends Data
{
    public function __construct(
        public string $barcode,
        public int $store_id,
        public ?string $name = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:255'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
