<?php

namespace App\Data\Tax;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;

class CreateTaxZoneData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required, Max(32), Unique('tax_zones', 'slug')]
        public string $slug,
        public ?string $description = null,
        public int $priority = 0,
        public bool $is_active = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:32', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:tax_zones,slug'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
