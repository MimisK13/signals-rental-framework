<?php

namespace App\Data\Tax;

use App\Enums\TaxCategoryType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Data;

class UpdateTaxCategoryData extends Data
{
    public function __construct(
        #[Sometimes, Max(255)]
        public ?string $name = null,
        #[Sometimes, Max(32)]
        public ?string $slug = null,
        public ?TaxCategoryType $type = null,
        public ?string $description = null,
        public ?bool $is_default = null,
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
            'type' => ['sometimes', 'string', 'in:standard,reduced,zero,exempt'],
            'description' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
