<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

class UpdateContainerData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $barcode = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
