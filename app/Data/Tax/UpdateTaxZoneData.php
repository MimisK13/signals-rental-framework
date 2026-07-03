<?php

namespace App\Data\Tax;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Data;

class UpdateTaxZoneData extends Data
{
    public function __construct(
        #[Sometimes, Max(255)]
        public ?string $name = null,
        #[Sometimes, Max(32)]
        public ?string $slug = null,
        public ?string $description = null,
        public ?int $priority = null,
        public ?bool $is_active = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:32', 'regex:/^[a-z][a-z0-9_]*$/'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
